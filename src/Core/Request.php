<?php

declare(strict_types=1);

namespace FileRoll\Core;

class Request
{
    private string $method;
    private string $uri;
    private array $query;
    private array $body;
    private array $server;
    private array $headers;
    public array $attributes = [];
    private ?array $uploadedFiles = null;

    public function __construct(
        ?string $method = null,
        ?string $uri = null,
        array $query = [],
        array $body = [],
        array $server = [],
        array $headers = []
    ) {
        $this->method = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $this->uri = $uri ?? $_SERVER['REQUEST_URI'] ?? '/';
        $this->query = $query ?: $_GET;
        $this->body = $body ?: $_POST;
        $this->server = $server ?: $_SERVER;
        $this->headers = $headers;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $query = $_GET;
        $body = [];

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $raw = file_get_contents('php://input');
                $body = json_decode($raw, true) ?? [];
            } else {
                $body = $_POST;
            }

            if (isset($body['_method'])) {
                $method = strtoupper($body['_method']);
                unset($body['_method']);
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        return new self($method, $uri, $query, $body, $_SERVER, $headers);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        $parsed = parse_url($this->uri);
        return $parsed['path'] ?? '/';
    }

    public function query(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function input(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $normalized = str_replace('-', '_', strtolower($name));
        foreach ($this->headers as $key => $value) {
            if (str_replace('-', '_', strtolower($key)) === $normalized) {
                return $value;
            }
        }
        return $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type', ''), 'application/json');
    }

    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function uploadedFiles(): array
    {
        if ($this->uploadedFiles === null) {
            $this->uploadedFiles = $_FILES ?? [];
        }
        return $this->uploadedFiles;
    }

    public function getIp(): string
    {
        $remoteAddr = $this->server('REMOTE_ADDR', '0.0.0.0');

        // Only trust X-Forwarded-For when the direct connection comes from localhost.
        // For production deployments behind a trusted reverse proxy, configure the
        // proxy to set REMOTE_ADDR correctly or extend this check.
        $forwarded = $this->server('HTTP_X_FORWARDED_FOR');
        if ($forwarded !== null && in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
            $ips = array_map('trim', explode(',', $forwarded));
            $first = $ips[0] ?? '';
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return $remoteAddr;
    }

    public function getUserAgent(): string
    {
        return $this->server('HTTP_USER_AGENT', '');
    }

    public function withMethod(string $method): self
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function withUri(string $uri): self
    {
        $clone = clone $this;
        $clone->uri = $uri;
        return $clone;
    }
}
