<?php

declare(strict_types=1);

namespace FileRoll\File;

use FileRoll\Core\Config;

class Storage
{
    private string $contentPath;
    private string $tempPath;
    private string $trashPath;

    public function __construct(Config $config)
    {
        $this->contentPath = $config->get('storage.content_path', __DIR__ . '/../../storage/content');
        $this->tempPath = $config->get('storage.temp_path', __DIR__ . '/../../storage/temp');
        $this->trashPath = $config->get('storage.trash_path', __DIR__ . '/../../storage/trash');

        $this->ensureDirectories();
    }

    public function save(string $tmpPath, ?string $expectedHash = null): array
    {
        if (!file_exists($tmpPath)) {
            throw new \RuntimeException('File not found: ' . $tmpPath);
        }

        $hash = hash_file('sha256', $tmpPath);

        if ($expectedHash !== null && $hash !== $expectedHash) {
            throw new \RuntimeException('File hash mismatch');
        }

        $blobPath = $this->getBlobPath($hash);

        if (!file_exists($blobPath)) {
            $dir = dirname($blobPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!rename($tmpPath, $blobPath)) {
                throw new \RuntimeException('Failed to move file to content store');
            }
        } else {
            @unlink($tmpPath);
        }

        return [
            'hash' => $hash,
            'path' => $blobPath,
            'size' => filesize($blobPath),
        ];
    }

    public function saveStream(string $content, string $mimeType = 'application/octet-stream'): array
    {
        $hash = hash('sha256', $content);
        $blobPath = $this->getBlobPath($hash);

        if (!file_exists($blobPath)) {
            $dir = dirname($blobPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($blobPath, $content);
        }

        return [
            'hash' => $hash,
            'path' => $blobPath,
            'size' => strlen($content),
        ];
    }

    public function get(string $hash): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }
        $path = $this->getBlobPath($hash);

        if (!file_exists($path)) {
            return null;
        }

        return file_get_contents($path);
    }

    public function getStream(string $hash)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }
        $path = $this->getBlobPath($hash);

        if (!file_exists($path)) {
            return null;
        }

        return fopen($path, 'rb');
    }

    public function getPath(string $hash): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }
        $path = $this->getBlobPath($hash);
        return file_exists($path) ? $path : null;
    }

    public function getSize(string $hash): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }
        $path = $this->getBlobPath($hash);
        return file_exists($path) ? filesize($path) : null;
    }

    public function exists(string $hash): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return false;
        }
        return file_exists($this->getBlobPath($hash));
    }

    public function delete(string $hash): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return false;
        }
        $path = $this->getBlobPath($hash);

        if (!file_exists($path)) {
            return true;
        }

        return unlink($path);
    }

    public function moveToTrash(string $hash): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return false;
        }
        $srcPath = $this->getBlobPath($hash);

        if (!file_exists($srcPath)) {
            return true;
        }

        $trashDir = $this->trashPath;
        if (!is_dir($trashDir)) {
            mkdir($trashDir, 0755, true);
        }

        $dstPath = $trashDir . '/' . $hash;
        return rename($srcPath, $dstPath);
    }

    public function getTempPath(string $filename): string
    {
        $tempDir = $this->tempPath;
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir . '/' . bin2hex(random_bytes(16)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    }

    public function getStorageStats(): array
    {
        $totalSize = 0;
        $totalFiles = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->contentPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $totalSize += $file->getSize();
            $totalFiles++;
        }

        return [
            'total_size' => $totalSize,
            'total_files' => $totalFiles,
            'content_path' => $this->contentPath,
        ];
    }

    private function getBlobPath(string $hash): string
    {
        $prefix = substr($hash, 0, 2);
        return $this->contentPath . '/' . $prefix . '/' . $hash;
    }

    private function ensureDirectories(): void
    {
        foreach ([$this->contentPath, $this->tempPath, $this->trashPath] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
