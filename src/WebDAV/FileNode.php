<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use FileRoll\Core\Config;
use FileRoll\Database\Connection;
use FileRoll\File\FileRepository;
use FileRoll\File\FileEntity;
use FileRoll\File\Storage;
use Sabre\DAV;

class FileNode implements DAV\IFile, DAV\IProperties, DAV\INode
{
    private Connection $db;
    private Config $config;
    private FileEntity $file;
    private int $userId;
    private FileRepository $fileRepo;

    public function __construct(Connection $db, Config $config, FileEntity $file, int $userId)
    {
        $this->db = $db;
        $this->config = $config;
        $this->file = $file;
        $this->userId = $userId;
        $this->fileRepo = new FileRepository($db);
    }

    public function getFileId(): int
    {
        return $this->file->id;
    }

    public function getFileEntity(): FileEntity
    {
        return $this->file;
    }

    public function getName(): string
    {
        return $this->file->name;
    }

    public function setName($name): void
    {
        $this->fileRepo->update($this->file->id, ['name' => $name]);
    }

    public function get(): string
    {
        $storage = new Storage($this->config);
        $content = $storage->get($this->file->contentHash);
        return $content ?? '';
    }

    public function put($data): string
    {
        $storage = new Storage($this->config);
        $tempPath = $storage->getTempPath($this->file->name);
        if (is_resource($data)) {
            $out = fopen($tempPath, 'wb');
            if ($out !== false) {
                stream_copy_to_stream($data, $out);
                fclose($out);
            }
        } else {
            file_put_contents($tempPath, $data);
        }

        $versionRepo = new \FileRoll\Version\VersionRepository($this->db);
        $fileService = new \FileRoll\File\FileService($this->fileRepo, $versionRepo, $storage, $this->db, $this->config);

        $fileService->upload($this->userId, $this->file->parentId, $tempPath, $this->file->name);

        $this->file = $this->fileRepo->findById($this->file->id) ?? $this->file;

        return $this->getETag();
    }

    public function getContentType(): string
    {
        return $this->file->mimeType ?? 'application/octet-stream';
    }

    public function getContentLength(): int
    {
        return $this->file->size;
    }

    public function getProperties($requestedProperties): array
    {
        $properties = [
            '{DAV:}displayname' => $this->file->name,
            '{DAV:}resourcetype' => '',
            '{DAV:}getcontenttype' => $this->file->mimeType ?? 'application/octet-stream',
            '{DAV:}getcontentlength' => $this->file->size,
            '{DAV:}getlastmodified' => $this->file->updatedAt ? strtotime($this->file->updatedAt) : time(),
            '{DAV:}getetag' => $this->getETag(),
            '{http://owncloud.org/ns}fileid' => (string) $this->file->id,
            '{http://owncloud.org/ns}id' => str_pad((string) $this->file->id, 8, '0', STR_PAD_LEFT) . 'oc',
            '{http://owncloud.org/ns}permissions' => 'RDNVW',
            '{http://owncloud.org/ns}size' => $this->file->size,
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

    public function getLastModified(): ?int
    {
        return $this->file->updatedAt ? strtotime($this->file->updatedAt) : time();
    }

    public function getETag(): ?string
    {
        return '"' . ($this->file->contentHash ?? md5($this->file->name)) . '"';
    }

    public function getSize(): int
    {
        return $this->file->size;
    }

    public function delete(): void
    {
        $versionRepo = new \FileRoll\Version\VersionRepository($this->db);
        $storage = new Storage($this->config);
        $fileService = new \FileRoll\File\FileService($this->fileRepo, $versionRepo, $storage, $this->db, $this->config);
        $fileService->delete($this->file->id, $this->userId);
    }
}
