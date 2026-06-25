<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use FileRoll\Core\Config;
use FileRoll\Database\Connection;
use FileRoll\File\FileRepository;
use Sabre\DAV;

class DirectoryNode implements DAV\ICollection, DAV\IProperties, DAV\INode
{
    private Connection $db;
    private Config $config;
    private ?int $fileId;
    private int $userId;
    private FileRepository $fileRepo;

    public function __construct(Connection $db, Config $config, ?int $fileId, int $userId)
    {
        $this->db = $db;
        $this->config = $config;
        $this->fileId = $fileId;
        $this->userId = $userId;
        $this->fileRepo = new FileRepository($db);
    }

    public function getFileId(): ?int
    {
        return $this->fileId;
    }

    public function getName(): string
    {
        if ($this->fileId === null) {
            return '';
        }
        $file = $this->fileRepo->findById($this->fileId);
        return $file?->name ?? '';
    }

    public function setName($name): void
    {
        if ($this->fileId !== null) {
            $this->fileRepo->update($this->fileId, ['name' => $name]);
        }
    }

    public function createFile($name, $data = null): string
    {
        $storage = new \FileRoll\File\Storage($this->config);
        $versionRepo = new \FileRoll\Version\VersionRepository($this->db);
        $fileService = new \FileRoll\File\FileService($this->fileRepo, $versionRepo, $storage, $this->db, $this->config);

        $tempPath = $storage->getTempPath($name);
        file_put_contents($tempPath, is_resource($data) ? stream_get_contents($data) : $data);

        $fileService->upload($this->userId, $this->fileId, $tempPath, $name);

        $file = $this->fileRepo->findByNameAndParent($name, $this->fileId, $this->userId);
        return $file ? ('"' . ($file->contentHash ?? md5($file->name)) . '"') : '"' . md5($name) . '"';
    }

    public function createDirectory($name): void
    {
        $storage = new \FileRoll\File\Storage($this->config);
        $versionRepo = new \FileRoll\Version\VersionRepository($this->db);
        $fileService = new \FileRoll\File\FileService($this->fileRepo, $versionRepo, $storage, $this->db, $this->config);

        $fileService->createFolder($this->userId, $this->fileId, $name);
    }

    public function getChild($name): DAV\INode
    {
        $file = $this->fileRepo->findByNameAndParent($name, $this->fileId, $this->userId);
        if ($file === null) {
            throw new DAV\Exception\NotFound("Node not found: {$name}");
        }

        if ($file->isFolder) {
            return new self($this->db, $this->config, $file->id, $this->userId);
        }

        return new FileNode($this->db, $this->config, $file, $this->userId);
    }

    public function getChildren(): array
    {
        $files = $this->fileRepo->listByParent($this->fileId, $this->userId);
        $children = [];

        foreach ($files as $file) {
            if ($file->isFolder) {
                $children[] = new self($this->db, $this->config, $file->id, $this->userId);
            } else {
                $children[] = new FileNode($this->db, $this->config, $file, $this->userId);
            }
        }

        return $children;
    }

    public function getProperties($requestedProperties): array
    {
        $fileId = $this->fileId ?? 0;
        $properties = [
            '{DAV:}displayname' => $this->getName(),
            '{DAV:}resourcetype' => '<d:collection/>',
            '{DAV:}getlastmodified' => $this->fileId !== null
                ? $this->getLastModified()
                : time(),
            '{DAV:}getetag' => $this->getETag(),
            '{http://owncloud.org/ns}fileid' => (string) $fileId,
            '{http://owncloud.org/ns}id' => str_pad((string) $fileId, 8, '0', STR_PAD_LEFT) . 'oc',
            '{http://owncloud.org/ns}permissions' => 'RDNVCK',
            '{http://owncloud.org/ns}size' => $this->getSize(),
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

    public function getLastModified(): ?int
    {
        if ($this->fileId === null) {
            return time();
        }
        $file = $this->fileRepo->findById($this->fileId);
        return $file?->updatedAt ? strtotime($file->updatedAt) : time();
    }

    public function getETag(): ?string
    {
        if ($this->fileId === null) {
            return '"' . md5('root') . '"';
        }
        $file = $this->fileRepo->findById($this->fileId);
        $ts = $file?->updatedAt ? strtotime($file->updatedAt) : time();
        return '"' . md5($this->fileId . '-' . $ts) . '"';
    }

    public function getSize(): int
    {
        return 0;
    }

    public function delete(): void
    {
        if ($this->fileId !== null) {
            $versionRepo = new \FileRoll\Version\VersionRepository($this->db);
            $storage = new \FileRoll\File\Storage($this->config);
            $fileService = new \FileRoll\File\FileService($this->fileRepo, $versionRepo, $storage, $this->db, $this->config);
            $fileService->delete($this->fileId, $this->userId);
        }
    }

    public function childExists($name): bool
    {
        $file = $this->fileRepo->findByNameAndParent($name, $this->fileId, $this->userId);
        return $file !== null;
    }

    public function propPatch(\Sabre\DAV\PropPatch $propPatch): void
    {
    }
}
