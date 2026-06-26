<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use FileRoll\Core\Container;
use FileRoll\Core\Config;
use FileRoll\Core\Request;
use FileRoll\Database\Connection;
use Sabre\DAV;
use Sabre\HTTP;

class Server
{
    public function handle(Request $request): void
    {
        $db = Container::getInstance()->get(Connection::class);
        $config = Container::getInstance()->get(Config::class);

        $tree = new FileBackend($db, $config);
        $server = new DAV\Server($tree);

        $rawUri = $_SERVER['REQUEST_URI'] ?? $request->uri();
        $base = defined('BASE_URL') ? BASE_URL : '';

        $isUploadsEndpoint = false;

        // Handle different WebDAV endpoint patterns
        // Priority: uploads endpoint first (for chunked uploads), then files endpoint
        if (preg_match('#^' . preg_quote($base, '#') . '/remote\.php/dav/uploads/([^/]+)(/|$)#', $rawUri, $m)) {
            // Chunked upload endpoint - baseUri includes user ID
            // Path will be relative to this, e.g. '<chunkId>/<chunkNum>'
            $baseUri = $base . '/remote.php/dav/uploads/' . $m[1] . '/';
            $isUploadsEndpoint = true;
        } elseif (preg_match('#^' . preg_quote($base, '#') . '/dav/uploads/([^/]+)(/|$)#', $rawUri, $m)) {
            $baseUri = $base . '/dav/uploads/' . $m[1] . '/';
            $isUploadsEndpoint = true;
        } elseif (preg_match('#^' . preg_quote($base, '#') . '/dav/files/([^/]+)(/|$)#', $rawUri, $m)) {
            $baseUri = $base . '/dav/files/' . $m[1] . '/';
        } elseif (preg_match('#^' . preg_quote($base, '#') . '/remote\.php/webdav(/|$)#', $rawUri)) {
            $baseUri = $base . '/remote.php/webdav/';
        } elseif (preg_match('#^' . preg_quote($base, '#') . '/remote\.php/dav/files/([^/]+)(/|$)#', $rawUri, $m)) {
            $baseUri = $base . '/remote.php/dav/files/' . $m[1] . '/';
        } elseif (preg_match('#^' . preg_quote($base, '#') . '/remote\.php/dav(/|$)#', $rawUri)) {
            $baseUri = $base . '/remote.php/dav/';
        } else {
            $baseUri = $base . '/dav/';
        }
        $server->setBaseUri($baseUri);

        if ($isUploadsEndpoint) {
            $tree->setUploadsMode(true);
        }

        $authBackend = new AuthBackend($db);
        $authPlugin = new DAV\Auth\Plugin($authBackend, 'FileRoll WebDAV');
        $server->addPlugin($authPlugin);

        $lockBackend = new LockBackend($db);
        $lockPlugin = new DAV\Locks\Plugin($lockBackend);
        $server->addPlugin($lockPlugin);

        $server->addPlugin(new ChunkingPlugin($db, $config));

        // Only expose the HTML browser interface in debug mode
        if ($config->get('app.debug', false)) {
            $server->addPlugin(new DAV\Browser\Plugin());
        }

        $server->httpRequest = self::mapRequest($request);
        $server->exec();
    }

    private static function mapRequest(Request $request): HTTP\Request
    {
        $method = $request->method();
        $rawUri = $_SERVER['REQUEST_URI'] ?? $request->uri();

        $httpRequest = new HTTP\Request($method, $rawUri);
        $httpRequest->setRawServerData($_SERVER);

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $httpRequest->setHeader($name, $value);
            }
        }

        $httpRequest->setHeader('Content-Type', $_SERVER['CONTENT_TYPE'] ?? '');

        // Use php://input stream instead of reading entire body into memory.
        // This avoids memory exhaustion on large chunk uploads (e.g. 10MB+ per chunk).
        // SabreDAV handles stream bodies correctly: small XML bodies (PROPFIND)
        // are read via getBodyAsString(), while PUT file data is streamed via
        // stream_copy_to_stream() in createFile()/put().
        $bodyStream = fopen('php://input', 'rb');
        $httpRequest->setBody($bodyStream !== false ? $bodyStream : '');

        return $httpRequest;
    }
}
