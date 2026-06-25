<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use FileRoll\Core\Config;
use FileRoll\Database\Connection;
use FileRoll\File\FileRepository;
use FileRoll\File\Storage;
use FileRoll\File\FileService;
use FileRoll\Version\VersionRepository;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class ChunkingPlugin extends ServerPlugin
{
    private Server $server;
    private Connection $db;
    private Config $config;

    public function __construct(Connection $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function initialize(Server $server): void
    {
        // Handle chunked-upload MOVE in beforeMethod (priority 30) so it runs
        // before checkPreconditions/validateTokens, which would otherwise throw
        // Forbidden because the Destination URI is on a different base URI
        // (e.g. source on /remote.php/dav/uploads/ but destination on /dav/files/).
        $server->on('beforeMethod:MOVE', [$this, 'beforeMethodMove'], 30);
        $this->server = $server;
    }

    public function beforeMethodMove(RequestInterface $request, ResponseInterface $response): ?bool
    {
        $path = $request->getPath();
        $baseUri = $this->server->getBaseUri();

        // Handle relative paths when baseUri is uploads endpoint
        // e.g. baseUri='/remote.php/dav/uploads/admin/', path='2617958187/.file'
        // Need to rewrite to 'uploads/<userId>/2617958187/.file' using the numeric user ID
        // because the upload temp directories are keyed by user ID, not username.
        // Only rewrite when the baseUri indicates we're in the uploads context,
        // otherwise regular file/folder names starting with digits get misrouted.
        $isUploadsBaseUri = str_contains($baseUri, '/uploads/');
        if ($isUploadsBaseUri && !str_starts_with($path, 'uploads/')) {
            if (preg_match('#^\d+(/|$)#', $path)) {
                $userId = $_SESSION['webdav_user_id'] ?? null;
                if ($userId !== null) {
                    $path = 'uploads/' . (int) $userId . '/' . $path;
                }
            }
        }

        // Only intercept chunked-upload MOVEs (source path like uploads/<user>/<id>/.file).
        if (!str_starts_with($path, 'uploads/')) {
            return null;
        }

        try {
            $sourceNode = $this->server->tree->getNodeForPath($path);
        } catch (\Exception $e) {
            return null;
        }

        if (!$sourceNode instanceof FutureFile) {
            return null;
        }

        $destination = $request->getHeader('Destination');
        if ($destination === null) {
            return null;
        }

        $normalized = $this->normalizeDestination($destination);

        try {
            $this->performMove($path, $normalized, $response);
        } catch (\Exception $e) {
            throw $e;
        }
        // Send the response and return false to stop propagation
        // (skip checkPreconditions/validateTokens which would throw Forbidden
        // because the Destination URI is on a different base URI).
        $this->server->sapi->sendResponse($response);
        return false;
    }

    private function normalizeDestination(string $destination): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $destination)) {
            $path = parse_url($destination, PHP_URL_PATH) ?? '';
        } else {
            $path = $destination;
        }

        $path = trim(preg_replace('#/+#', '/', $path), '/');

        $base = defined('BASE_URL') ? BASE_URL : '';
        if ($base !== '') {
            $base = trim($base, '/');
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
                $path = trim($path, '/');
            }
        }

        // Strip WebDAV endpoint prefixes to get the logical file path.
        // Supported patterns:
        //   dav/files/<user>/<path>
        //   remote.php/dav/files/<user>/<path>
        //   remote.php/webdav/<path>
        //   webdav/<path>
        if (preg_match('#^(?:remote\.php/)?dav/files/[^/]+/(.*)$#', $path, $m)) {
            $path = $m[1];
        } elseif (preg_match('#^(?:remote\.php/)?webdav/(.*)$#', $path, $m)) {
            $path = $m[1];
        }

        return $path;
    }

    private function performMove(string $sourcePath, string $destination, ResponseInterface $response): void
    {

        $sourceNode = $this->server->tree->getNodeForPath($sourcePath);
        if (!$sourceNode instanceof FutureFile) {
            return;
        }

        $uploadFolder = $sourceNode->getFolder();

        // Enforce that the authenticated user can only finalize their own uploads
        $authUserId = $_SESSION['webdav_user_id'] ?? null;
        if ($authUserId === null || (int) $authUserId !== $uploadFolder->getUserId()) {
            throw new DAV\Exception\Forbidden('Cannot access upload folder for another user');
        }

        $chunks = [];
        foreach ($uploadFolder->getChildren() as $child) {
            if ($child instanceof UploadFileNode && $child->getName() !== '.file') {
                $chunks[] = $child;
            }
        }
        usort($chunks, fn($a, $b) => strcmp($a->getName(), $b->getName()));

        $storage = new Storage($this->config);
        $tempPath = $storage->getTempPath('chunk_assembled');
        $out = fopen($tempPath, 'wb');
        foreach ($chunks as $chunk) {
            $chunkPath = $chunk->getFilePath();
            if (file_exists($chunkPath)) {
                $in = fopen($chunkPath, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        }
        fclose($out);

        $destParts = explode('/', trim($destination, '/'));
        $destName = end($destParts);
        $destParentPath = implode('/', array_slice($destParts, 0, -1));

        $userId = $uploadFolder->getUserId();

        $fileRepo = new FileRepository($this->db);
        $versionRepo = new VersionRepository($this->db);
        $fileService = new FileService($fileRepo, $versionRepo, $storage, $this->db, $this->config);

        $destParentId = null;
        // Temporarily disable uploads mode so that numeric directory names
        // (e.g. "4444444") in the destination path are resolved against the
        // file tree instead of being misrouted to the uploads temp area.
        $backend = $this->server->tree;
        $needsRestore = false;
        if ($backend instanceof FileBackend) {
            $needsRestore = true;
            $backend->setUploadsMode(false);
        }
        try {
            if ($destParentPath !== '') {
                $parentNode = $this->server->tree->getNodeForPath($destParentPath);
                if ($parentNode instanceof DirectoryNode) {
                    $destParentId = $parentNode->getFileId();
                }
            }

            $fileExists = $this->server->tree->nodeExists($destination);
        } finally {
            if ($needsRestore) {
                $backend->setUploadsMode(true);
            }
        }

        $uploadedFile = $fileService->upload($userId, $destParentId, $tempPath, $destName);
        $fileId = $uploadedFile->id;

        $uploadFolder->delete();

        $eTag = '"' . ($uploadedFile->contentHash ?? md5($uploadedFile->name)) . '"';
        $response->setHeader('Content-Length', '0');
        $response->setHeader('OC-FileId', (string)$fileId);
        $response->setHeader('ETag', $eTag);
        $response->setStatus($fileExists ? 204 : 201);
    }
}
