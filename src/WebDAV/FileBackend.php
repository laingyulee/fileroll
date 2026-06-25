<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use FileRoll\Core\Config;
use FileRoll\Database\Connection;
use FileRoll\File\FileRepository;
use FileRoll\File\Storage;
use FileRoll\File\FileService;
use FileRoll\Version\VersionRepository;
use Sabre\DAV;

class FileBackend extends DAV\Tree
{
    private Connection $db;
    private Config $config;
    private FileRepository $fileRepo;
    private Storage $storage;
    private bool $uploadsMode = false;

    public function __construct(Connection $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->fileRepo = new FileRepository($db);
        $this->storage = new Storage($config);

        $rootNode = new DirectoryNode($db, $config, null, 0);
        parent::__construct($rootNode);
    }

    public function setUploadsMode(bool $enabled): void
    {
        $this->uploadsMode = $enabled;
    }

    public function getNodeForPath($path)
    {
        $path = trim($path, '/');
        $userId = $this->getCurrentUserId();

        if ($path === '') {
            if ($this->uploadsMode) {
                return new UploadDirectoryNode($this->getUploadsPath('uploads/' . $userId), $userId);
            }
            return new DirectoryNode($this->db, $this->config, null, $userId);
        }

        // Handle upload paths that don't start with 'uploads/' prefix
        // This happens when baseUri is set to /remote.php/dav/uploads/<user>/
        // The path is relative: '<chunkId>' or '<chunkId>/<chunkNum>' or '<chunkId>/.file'
        // We need to prepend 'uploads/<userId>/'
        // IMPORTANT: only apply this rewrite in uploads mode, otherwise legitimate
        // file/folder names that start with digits (e.g. "111", "2024") get misrouted.
        if ($this->uploadsMode && !str_starts_with($path, 'uploads/')) {
            if (preg_match('#^\d+(/|$)#', $path)) {
                $path = 'uploads/' . $userId . '/' . $path;
            }
        }

        if (str_starts_with($path, 'uploads/')) {
            $rest = substr($path, strlen('uploads/'));
            $segments = array_values(array_filter(explode('/', $rest), fn($s) => $s !== ''));
            $last = end($segments);

            if ($last === '.file') {
                $dirPath = $this->getUploadsPath(dirname($path));
                $dirNode = new UploadDirectoryNode(rtrim($dirPath, '/'), $userId);
                return new FutureFile($dirNode);
            }

            $uploadsPath = $this->getUploadsPath($path);
            if (!file_exists($uploadsPath)) {
                // Returning the parent directory here would make nodeExists() believe
                // the requested path already exists, causing SabreDAV to call put() on
                // a collection instead of createFile() on the parent. Throw NotFound so
                // the caller (e.g. PUT/MKCOL plugin) resolves the parent and creates the
                // resource through the proper collection interface.
                throw new DAV\Exception\NotFound("Upload node not found: {$path}");
            }
            if (is_dir($uploadsPath)) {
                return new UploadDirectoryNode($uploadsPath, $userId);
            }
            return new UploadFileNode($uploadsPath);
        }

        $parts = explode('/', $path);
        $currentParentId = null;
        $currentNode = null;

        foreach ($parts as $part) {
            $file = $this->fileRepo->findByNameAndParent($part, $currentParentId, $userId);
            if ($file === null) {
                throw new DAV\Exception\NotFound("Node not found: {$path}");
            }

            if ($file->isFolder) {
                $currentNode = new DirectoryNode($this->db, $this->config, $file->id, $userId);
                $currentParentId = $file->id;
            } else {
                return new FileNode($this->db, $this->config, $file, $userId);
            }
        }

        return $currentNode ?? new DirectoryNode($this->db, $this->config, null, $userId);
    }

    public function getChildren($path)
    {
        $node = $this->getNodeForPath($path);
        if (!$node instanceof DirectoryNode && !$node instanceof UploadDirectoryNode) {
            throw new DAV\Exception\NotADirectory("Not a directory: {$path}");
        }
        return $node->getChildren();
    }

    public function createDirectory($path)
    {
        $userId = $this->getCurrentUserId();

        // Handle relative paths for uploads endpoint
        if (!str_starts_with($path, 'uploads/')) {
            if (preg_match('#^\d+(/|$)#', $path)) {
                $path = 'uploads/' . $userId . '/' . $path;
            }
        }

        if (str_starts_with($path, 'uploads/')) {
            $fullPath = $this->getUploadsPath($path);
            if (!is_dir($fullPath)) {
                $created = mkdir($fullPath, 0755, true);
            }
            return;
        }
        parent::createDirectory($path);
    }

    public function copy($source, $destination)
    {
        $userId = $this->getCurrentUserId();
        $versionRepo = new VersionRepository($this->db);
        $fileService = new FileService($this->fileRepo, $versionRepo, $this->storage, $this->db, $this->config);

        $sourceNode = $this->getNodeForPath($source);
        $destParts = explode('/', trim($destination, '/'));
        $destName = end($destParts);
        $destParentPath = implode('/', array_slice($destParts, 0, -1));
        $destParent = $destParentPath !== '' ? $this->getNodeForPath($destParentPath) : null;
        $destParentId = $destParent instanceof DirectoryNode ? $destParent->getFileId() : null;

        if ($sourceNode instanceof FileNode) {
            $fileService->copy($sourceNode->getFileId(), $userId, $destParentId);
        } elseif ($sourceNode instanceof DirectoryNode) {
            $fileService->copy($sourceNode->getFileId(), $userId, $destParentId);
        }
    }

    public function move($source, $destination)
    {
        $userId = $this->getCurrentUserId();
        $versionRepo = new VersionRepository($this->db);
        $fileService = new FileService($this->fileRepo, $versionRepo, $this->storage, $this->db, $this->config);

        $sourceNode = $this->getNodeForPath($source);

        $destParts = explode('/', trim($destination, '/'));
        $destParentPath = implode('/', array_slice($destParts, 0, -1));
        $destParent = $destParentPath !== '' ? $this->getNodeForPath($destParentPath) : null;
        $destParentId = $destParent instanceof DirectoryNode ? $destParent->getFileId() : null;

        $fileService->move($sourceNode->getFileId(), $userId, $destParentId);

        $destName = end($destParts);
        if ($destName !== $sourceNode->getName()) {
            $fileService->rename($sourceNode->getFileId(), $userId, $destName);
        }
    }

    private function getUploadsPath(string $path): string
    {
        $tempPath = $this->config->get('storage.temp_path', dirname(__DIR__, 2) . '/storage/temp');
        $base = rtrim($tempPath, '/') . '/webdav_uploads/';

        // Normalize directory separators and URL-decode to catch encoded traversal
        $path = str_replace('\\', '/', $path);
        $path = rawurldecode($path);

        // Repeatedly strip path traversal sequences until stable
        do {
            $previous = $path;
            $path = preg_replace('#/\.+(/|$)#', '/', $path);
            $path = preg_replace('#^\.+/+#', '', $path);
        } while ($path !== $previous);

        // Prevent null bytes
        $path = str_replace("\0", '', $path);

        return $base . ltrim($path, '/');
    }

    public function getNodeProperties(string $path, array $requestedProperties): array
    {
        $node = $this->getNodeForPath($path);
        return $node->getProperties($requestedProperties);
    }

    public function setNodeProperties(string $path, array $properties): void
    {
        $node = $this->getNodeForPath($path);
        $node->setProperties($properties);
    }

    public function delete($path)
    {
        $userId = $this->getCurrentUserId();

        $node = $this->getNodeForPath($path);

        if ($node instanceof UploadDirectoryNode || $node instanceof UploadFileNode) {
            $node->delete();
            return;
        }

        $versionRepo = new VersionRepository($this->db);
        $fileService = new FileService($this->fileRepo, $versionRepo, $this->storage, $this->db, $this->config);
        $fileService->delete($node->getFileId(), $userId);
    }

    public function getSize(string $path): int
    {
        $node = $this->getNodeForPath($path);
        if ($node instanceof FileNode || $node instanceof UploadFileNode) {
            return $node->getSize();
        }
        return 0;
    }

    public function getETag(string $path): ?string
    {
        $node = $this->getNodeForPath($path);
        return $node->getETag();
    }

    public function getLastModified(string $path): ?int
    {
        $node = $this->getNodeForPath($path);
        return $node->getLastModified();
    }

    public function getName(string $path): string
    {
        $parts = explode('/', trim($path, '/'));
        return end($parts) ?: '';
    }

    private function getCurrentUserId(): int
    {
        $userId = $_SESSION['webdav_user_id'] ?? null;
        if ($userId === null) {
            throw new DAV\Exception\NotAuthenticated('User not authenticated');
        }
        return (int) $userId;
    }
}
