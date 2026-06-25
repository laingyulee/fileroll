<?php

declare(strict_types=1);

namespace FileRoll\Core;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';
    private array $cookies = [];

    public function statusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function json(mixed $data, int $statusCode = 200): self
    {
        $this->statusCode = $statusCode;
        $this->header('Content-Type', 'application/json; charset=utf-8');
        $this->body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        return $this;
    }

    public function html(string $html, int $statusCode = 200): self
    {
        $this->statusCode = $statusCode;
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $this->body = $html;
        return $this;
    }

    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->statusCode = $statusCode;
        $this->header('Location', $url);
        $this->body = '';
        return $this;
    }

    public function file(string $path, ?string $filename = null, ?string $mimeType = null): self
    {
        if (!file_exists($path)) {
            return $this->html('File not found', 404);
        }

        $this->statusCode = 200;
        $this->header('Content-Type', $mimeType ?? mime_content_type($path) ?: 'application/octet-stream');
        $this->header('Content-Length', (string) filesize($path));
        $this->header('Content-Disposition', 'inline; filename="' . self::sanitizeFilename($filename ?? basename($path)) . '"');
        $this->header('Cache-Control', 'private, max-age=3600');

        $this->body = file_get_contents($path);
        return $this;
    }

    public function stream(string $path, ?string $filename = null, ?string $mimeType = null): void
    {
        if (!file_exists($path)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $mimeType = $mimeType ?? mime_content_type($path) ?: 'application/octet-stream';
        $filesize = filesize($path);
        $filename = $filename ?? basename($path);

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $filesize);
        header('Content-Disposition: inline; filename="' . self::sanitizeFilename($filename) . '"');
        header('Cache-Control: private, max-age=3600');

        readfile($path);
        exit;
    }

    public function cookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[$name] = ['value' => $value, 'options' => $options];
        return $this;
    }

    public function deleteCookie(string $name): self
    {
        $this->cookies[$name] = ['value' => '', 'options' => ['expires' => time() - 3600]];
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        foreach ($this->cookies as $name => $cookie) {
            setcookie($name, $cookie['value'], $cookie['options']);
        }

        echo $this->body;
    }

    public static function json_response(mixed $data, int $statusCode = 200): self
    {
        return (new self())->json($data, $statusCode);
    }

    public static function error(int $code, string $message = ''): self
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        ];
        $message = $message ?: ($messages[$code] ?? 'Error');
        return (new self())->json(['error' => true, 'message' => $message], $code);
    }

    private static function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(["\r", "\n", "\t"], '_', $filename);
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename);
        $filename = trim($filename, '_. ');
        return $filename !== '' ? $filename : 'file';
    }
}
