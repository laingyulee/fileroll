<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use FileRoll\WebDAV\Sanitizer;

use Sabre\DAV;

class UploadDirectoryNode implements DAV\ICollection, DAV\IProperties
{
    private string $path;
    private int $userId;

    public function __construct(string $path, int $userId)
    {
        $this->path = rtrim($path, '/');
        $this->userId = $userId;
    }

    public function getFilePath(): string
    {
        return $this->path;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return basename($this->path) ?: '';
    }

    public function getLastModified(): ?int
    {
        return file_exists($this->path) ? filemtime($this->path) : time();
    }

    public function getETag(): ?string
    {
        $ts = $this->getLastModified();
        return '"' . md5($this->path . '-' . $ts) . '"';
    }

    public function createFile($name, $data = null): ?string
    {
        $name = Sanitizer::filename((string) $name);
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
        $filePath = $this->path . '/' . $name;
        if (is_resource($data)) {
            $out = fopen($filePath, 'wb');
            if ($out !== false) {
                stream_copy_to_stream($data, $out);
                fclose($out);
            }
        } else {
            file_put_contents($filePath, $data);
        }
        return '"' . md5($name) . '"';
    }

    public function createDirectory($name): void
    {
        $name = Sanitizer::filename((string) $name);
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
        $newPath = $this->path . '/' . $name;
        if (!is_dir($newPath)) {
            mkdir($newPath, 0755, true);
        }
    }

    public function getChild($name): DAV\INode
    {
        if ($name === '.file') {
            return new FutureFile($this);
        }
        $childPath = $this->path . '/' . $name;
        if (!file_exists($childPath)) {
            throw new DAV\Exception\NotFound("Node not found: {$name}");
        }
        if (is_dir($childPath)) {
            return new self($childPath, $this->userId);
        }
        return new UploadFileNode($childPath);
    }

    public function getChildren(): array
    {
        $children = [new FutureFile($this)];
        if (!is_dir($this->path)) {
            return $children;
        }
        $items = scandir($this->path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $childPath = $this->path . '/' . $item;
            if (is_dir($childPath)) {
                $children[] = new self($childPath, $this->userId);
            } else {
                $children[] = new UploadFileNode($childPath);
            }
        }
        return $children;
    }

    public function childExists($name): bool
    {
        if ($name === '.file') {
            return true;
        }
        return file_exists($this->path . '/' . $name);
    }

    public function setName($name): void
    {
        $name = Sanitizer::filename((string) $name);
        $newPath = dirname($this->path) . '/' . $name;
        if (is_dir($this->path)) {
            rename($this->path, $newPath);
        }
        $this->path = $newPath;
    }

    public function delete(): void
    {
        $this->rmdirRecursive($this->path);
    }

    public function getProperties($requestedProperties): array
    {
        $properties = [
            '{DAV:}displayname' => $this->getName(),
            '{DAV:}resourcetype' => '<d:collection/>',
            '{DAV:}getlastmodified' => $this->getLastModified(),
            '{DAV:}getetag' => $this->getETag(),
            '{http://owncloud.org/ns}fileid' => (string) crc32($this->path),
            '{http://owncloud.org/ns}id' => str_pad(dechex(crc32($this->path)), 8, '0', STR_PAD_LEFT) . 'oc',
            '{http://owncloud.org/ns}permissions' => 'RDNVCK',
            '{http://owncloud.org/ns}size' => 0,
            '{DAV:}getcontentlength' => 0,
        ];

        $result = [];
        foreach ($requestedProperties as $prop) {
            $result[$prop] = $properties[$prop] ?? null;
        }
        return $result;
    }

    public function setProperties(array $properties): void
    {
    }

    public function propPatch(\Sabre\DAV\PropPatch $propPatch): void
    {
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $dir . '/' . $item;
            if (is_dir($fullPath)) {
                $this->rmdirRecursive($fullPath);
            } else {
                unlink($fullPath);
            }
        }
        rmdir($dir);
    }
}
