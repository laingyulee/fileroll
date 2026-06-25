<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use FileRoll\WebDAV\Sanitizer;

use Sabre\DAV;

class UploadFileNode implements DAV\IFile, DAV\IProperties
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function getFilePath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return basename($this->path);
    }

    public function getLastModified(): ?int
    {
        return file_exists($this->path) ? filemtime($this->path) : null;
    }

    public function getETag(): ?string
    {
        if (!file_exists($this->path)) {
            return null;
        }
        return '"' . md5($this->path . '-' . filemtime($this->path)) . '"';
    }

    public function getSize(): int
    {
        return file_exists($this->path) ? filesize($this->path) : 0;
    }

    public function getContentType(): ?string
    {
        return null;
    }

    public function get()
    {
        if (!file_exists($this->path)) {
            throw new DAV\Exception\NotFound('File not found');
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new DAV\Exception\NotFound('File not found');
        }

        return $handle;
    }

    public function put($data): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (is_resource($data)) {
            $out = fopen($this->path, 'wb');
            stream_copy_to_stream($data, $out);
            fclose($out);
        } else {
            file_put_contents($this->path, $data);
        }
    }

    public function delete(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    public function setName($name): void
    {
        $name = Sanitizer::filename((string) $name);
        $newPath = dirname($this->path) . '/' . $name;
        rename($this->path, $newPath);
        $this->path = $newPath;
    }

    public function getProperties($requestedProperties): array
    {
        $properties = [
            '{DAV:}displayname' => $this->getName(),
            '{DAV:}resourcetype' => null,
            '{DAV:}getlastmodified' => $this->getLastModified(),
            '{DAV:}getetag' => $this->getETag(),
            '{DAV:}getcontenttype' => $this->getContentType(),
            '{DAV:}getcontentlength' => (string) $this->getSize(),
            '{http://owncloud.org/ns}fileid' => (string) crc32($this->path),
            '{http://owncloud.org/ns}id' => str_pad(dechex(crc32($this->path)), 8, '0', STR_PAD_LEFT) . 'oc',
            '{http://owncloud.org/ns}permissions' => 'RDNVW',
            '{http://owncloud.org/ns}size' => (string) $this->getSize(),
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
}
