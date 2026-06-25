<?php

declare(strict_types=1);

namespace FileRoll\File;

use FileRoll\Core\Config;
use FileRoll\Database\Connection;
use FileRoll\Version\VersionRepository;

class FileService
{
    private FileRepository $fileRepo;
    private VersionRepository $versionRepo;
    private Storage $storage;
    private Connection $db;
    private Config $config;

    public function __construct(
        FileRepository $fileRepo,
        VersionRepository $versionRepo,
        Storage $storage,
        Connection $db,
        Config $config
    ) {
        $this->fileRepo = $fileRepo;
        $this->versionRepo = $versionRepo;
        $this->storage = $storage;
        $this->db = $db;
        $this->config = $config;
    }

    public function upload(int $userId, ?int $parentId, string $tmpPath, string $filename): FileEntity
    {
        $filename = $this->sanitizeFilename($filename);
        $this->checkQuota($userId, $tmpPath);

        $existing = $this->fileRepo->findByNameAndParent($filename, $parentId, $userId);

        $mimeType = $this->detectMimeType($tmpPath, $filename);

        $result = $this->storage->save($tmpPath);

        if ($existing !== null && !$existing->isFolder) {
            $this->createVersion($existing, $userId);
            $this->fileRepo->update($existing->id, [
                'content_hash' => $result['hash'],
                'storage_path' => $result['path'],
                'size' => (string) $result['size'],
                'mime_type' => $mimeType,
            ]);
            $this->touchParent($parentId);
            return $this->fileRepo->findById($existing->id);
        }

        $resultFile = $this->fileRepo->create([
            'parent_id' => $parentId !== null ? (string) $parentId : null,
            'name' => $filename,
            'mime_type' => $mimeType,
            'size' => (string) $result['size'],
            'is_folder' => 0,
            'content_hash' => $result['hash'],
            'storage_path' => $result['path'],
            'owner_id' => (string) $userId,
            'created_by' => (string) $userId,
        ]);
        $this->touchParent($parentId);
        return $resultFile;
    }

    public function createFolder(int $userId, ?int $parentId, string $name): FileEntity
    {
        $name = $this->sanitizeFilename($name);
        $existing = $this->fileRepo->findByNameAndParent($name, $parentId, $userId);
        if ($existing !== null) {
            throw new \RuntimeException(t('error.folder_exists'));
        }

        $folder = $this->fileRepo->create([
            'parent_id' => $parentId !== null ? (string) $parentId : null,
            'name' => $name,
            'is_folder' => 1,
            'size' => 0,
            'owner_id' => (string) $userId,
            'created_by' => (string) $userId,
        ]);
        $this->touchParent($parentId);
        return $folder;
    }

    public function createTextFile(int $userId, ?int $parentId, string $name, string $content = ''): FileEntity
    {
        $name = $this->sanitizeFilename($name);
        $existing = $this->fileRepo->findByNameAndParent($name, $parentId, $userId);
        if ($existing !== null) {
            throw new \RuntimeException(t('error.file_exists'));
        }

        $mimeType = $this->detectMimeTypeForName($name);
        $result = $this->storage->saveStream($content, $mimeType);

        $file = $this->fileRepo->create([
            'parent_id' => $parentId !== null ? (string) $parentId : null,
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => (string) $result['size'],
            'is_folder' => 0,
            'content_hash' => $result['hash'],
            'storage_path' => $result['path'],
            'owner_id' => (string) $userId,
            'created_by' => (string) $userId,
        ]);
        $this->touchParent($parentId);
        return $file;
    }

    private function detectMimeTypeForName(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'markdown' => 'text/markdown',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'yaml' => 'text/yaml',
            'yml' => 'text/yaml',
            'csv' => 'text/csv',
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'mjs' => 'application/javascript',
            'ts' => 'application/typescript',
            'jsx' => 'application/javascript',
            'tsx' => 'application/typescript',
            'php' => 'application/x-httpd-php',
            'py' => 'text/x-python',
            'rb' => 'text/x-ruby',
            'java' => 'text/x-java',
            'c' => 'text/x-c',
            'cpp' => 'text/x-c++',
            'h' => 'text/x-c',
            'cs' => 'text/x-csharp',
            'go' => 'text/x-go',
            'rs' => 'text/x-rust',
            'swift' => 'text/x-swift',
            'kt' => 'text/x-kotlin',
            'sh' => 'application/x-sh',
            'bash' => 'application/x-sh',
            'sql' => 'application/sql',
            'r' => 'text/x-r',
            'lua' => 'text/x-lua',
            'dart' => 'text/x-dart',
            'toml' => 'text/plain',
            'ini' => 'text/plain',
            'cfg' => 'text/plain',
            'conf' => 'text/plain',
            'log' => 'text/plain',
            'dockerfile' => 'text/plain',
            'makefile' => 'text/plain',
        ];
        return $map[$ext] ?? 'text/plain';
    }

    public function delete(int $fileId, int $userId): array
    {
        $file = $this->fileRepo->findByIdAndOwnerAny($fileId, $userId);
        if ($file === null) {
            return ['success' => false, 'message' => t('error.file_not_found')];
        }

        $parentId = $file->parentId;

        if ($file->isFolder) {
            $children = $this->fileRepo->listByParent($file->id, $userId);
            foreach ($children as $child) {
                $this->delete($child->id, $userId);
            }
        }

        $this->db->beginTransaction();
        try {
            if (!$file->isFolder && $file->contentHash !== null) {
                $this->versionRepo->deleteByFileId($file->id);
                $physicalExists = $this->storage->exists($file->contentHash);
                $this->storage->delete($file->contentHash);
            } else {
                $physicalExists = null;
            }
            $this->fileRepo->delete($file->id);
            $this->db->commit();

            $this->touchParent($parentId);

            if ($physicalExists === false) {
                return ['success' => true, 'missing' => true, 'message' => t('files.missing_cleaned')];
            }
            return ['success' => true, 'missing' => false];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function trash(int $fileId, int $userId): bool
    {
        $file = $this->fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return false;
        }

        $parentId = $file->parentId;
        $this->trashRecursive($file, $userId);
        $this->touchParent($parentId);
        return true;
    }

    public function emptyTrash(int $userId): int
    {
        $trashed = $this->fileRepo->listTrashed($userId);
        $count = 0;
        foreach ($trashed as $file) {
            $this->delete($file->id, $userId);
            $count++;
        }
        return $count;
    }

    public function restore(int $fileId, int $userId): bool
    {
        $file = $this->fileRepo->findById($fileId);
        if ($file === null || $file->ownerId !== $userId || !$file->isTrashed) {
            return false;
        }

        $this->restoreRecursive($file, $userId);
        $this->touchParent($file->parentId);
        return true;
    }

    public function rename(int $fileId, int $userId, string $newName): bool
    {
        $file = $this->fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return false;
        }

        $existing = $this->fileRepo->findByNameAndParent($newName, $file->parentId, $userId);
        if ($existing !== null && $existing->id !== $fileId) {
            throw new \RuntimeException(t('error.file_exists'));
        }

        $sanitizedName = $this->sanitizeFilename($newName);
        $result = $this->fileRepo->update($fileId, ['name' => $sanitizedName]);
        $this->touchParent($file->parentId);
        return $result;
    }

    public function move(int $fileId, int $userId, ?int $targetFolderId): bool
    {
        $file = $this->fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return false;
        }

        $oldParentId = $file->parentId;

        if ($targetFolderId !== null) {
            if ($targetFolderId === $fileId) {
                throw new \RuntimeException(t('error.cannot_move_into_self'));
            }

            $target = $this->fileRepo->findByIdAndOwner($targetFolderId, $userId);
            if ($target === null || !$target->isFolder) {
                throw new \RuntimeException(t('error.invalid_target'));
            }

            if ($this->isChildOf($fileId, $targetFolderId, $userId)) {
                throw new \RuntimeException(t('error.cannot_move_into_self'));
            }
        }

        $existing = $this->fileRepo->findByNameAndParent($file->name, $targetFolderId, $userId);
        if ($existing !== null && $existing->id !== $fileId) {
            throw new \RuntimeException(t('error.file_exists_target'));
        }

        $result = $this->fileRepo->update($fileId, ['parent_id' => $targetFolderId !== null ? (string) $targetFolderId : null]);
        $this->touchParent($oldParentId);
        $this->touchParent($targetFolderId);
        return $result;
    }

    public function copy(int $fileId, int $userId, ?int $targetFolderId): ?FileEntity
    {
        $file = $this->fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return null;
        }

        if ($targetFolderId !== null) {
            if ($targetFolderId === $fileId || $this->isChildOf($fileId, $targetFolderId, $userId)) {
                throw new \RuntimeException(t('error.cannot_copy_into_self'));
            }
        }

        if ($file->isFolder) {
            $newFolder = $this->createFolder($userId, $targetFolderId, $file->name . ' (copy)');
            $children = $this->fileRepo->listByParent($file->id, $userId);
            foreach ($children as $child) {
                $this->copy($child->id, $userId, $newFolder->id);
            }
            $this->touchParent($targetFolderId);
            return $newFolder;
        }

        $copyName = $this->getCopyName($file->name, $targetFolderId, $userId);

        $result = $this->fileRepo->create([
            'parent_id' => $targetFolderId !== null ? (string) $targetFolderId : null,
            'name' => $copyName,
            'mime_type' => $file->mimeType,
            'size' => (string) $file->size,
            'is_folder' => 0,
            'content_hash' => $file->contentHash,
            'storage_path' => $file->storagePath,
            'owner_id' => (string) $userId,
            'created_by' => (string) $userId,
        ]);
        $this->touchParent($targetFolderId);
        return $result;
    }

    public function toggleStar(int $fileId, int $userId): bool
    {
        $file = $this->fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return false;
        }

        return $this->fileRepo->update($fileId, ['is_starred' => $file->isStarred ? 0 : 1]);
    }

    public function getFileContent(int $fileId, int $userId): ?string
    {
        $file = $this->fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null || $file->isFolder || $file->contentHash === null) {
            return null;
        }

        return $this->storage->get($file->contentHash);
    }

    public function getFileStream(int $fileId, int $userId)
    {
        $file = $this->fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null || $file->isFolder || $file->contentHash === null) {
            return null;
        }

        return $this->storage->getStream($file->contentHash);
    }

    public function getFilePath(int $fileId, int $userId): ?string
    {
        $file = $this->fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null || $file->isFolder || $file->contentHash === null) {
            return null;
        }

        return $this->storage->getPath($file->contentHash);
    }

    public function checkQuota(int $userId, string $tmpPath): void
    {
        $userRepo = new \FileRoll\User\UserRepository($this->db);
        $user = $userRepo->findById($userId);

        if ($user === null) {
            throw new \RuntimeException(t('error.user_not_found'));
        }

        $fileSize = filesize($tmpPath);
        $used = $this->fileRepo->getStorageUsed($userId);

        if ($user->storageQuota > 0 && $used + $fileSize > $user->storageQuota) {
            throw new \RuntimeException(t('error.quota_exceeded'));
        }
    }

    public function getBreadcrumb(int $fileId, int $userId): array
    {
        $path = $this->fileRepo->getPath($fileId);
        return array_filter($path, fn($f) => $f->ownerId === $userId);
    }

    private function createVersion(FileEntity $file, int $userId): void
    {
        $latestVersion = $this->versionRepo->getLatestVersionNumber($file->id);
        $newVersionNumber = $latestVersion + 1;

        $this->versionRepo->create([
            'file_id' => (string) $file->id,
            'version_number' => (string) $newVersionNumber,
            'content_hash' => $file->contentHash,
            'storage_path' => $file->storagePath,
            'size' => (string) $file->size,
            'created_by' => (string) $userId,
        ]);
    }

    private function trashRecursive(FileEntity $file, int $userId): void
    {
        if ($file->isFolder) {
            $children = $this->fileRepo->listByParent($file->id, $userId);
            foreach ($children as $child) {
                $this->trashRecursive($child, $userId);
            }
        }
        $this->fileRepo->trash($file->id);
    }

    private function restoreRecursive(FileEntity $file, int $userId): void
    {
        $this->fileRepo->restore($file->id);

        $allChildren = $this->db->fetchAll(
            'SELECT * FROM files WHERE parent_id = ? AND owner_id = ? AND is_trashed = 1',
            [$file->id, $userId]
        );

        foreach ($allChildren as $childData) {
            $child = FileEntity::fromArray($childData);
            $this->restoreRecursive($child, $userId);
        }
    }

    private function isChildOf(int $parentId, int $childId, int $userId): bool
    {
        $currentId = $childId;
        while ($currentId !== null) {
            if ($currentId === $parentId) {
                return true;
            }
            $file = $this->fileRepo->findById($currentId);
            if ($file === null) break;
            $currentId = $file->parentId;
        }
        return false;
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[<>:"\/\\|?*]/', '_', $filename);
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        $filename = trim($filename, '. ');
        return $filename !== '' ? $filename : 'unnamed';
    }

    private function getCopyName(string $name, ?int $parentId, int $userId): string
    {
        $pathinfo = pathinfo($name);
        $baseName = $pathinfo['filename'] ?? $name;
        $extension = $pathinfo['extension'] ?? '';
        $suffix = 1;

        while (true) {
            $candidate = $extension !== '' ? "{$baseName} ({$suffix}).{$extension}" : "{$baseName} ({$suffix})";
            $existing = $this->fileRepo->findByNameAndParent($candidate, $parentId, $userId);
            if ($existing === null) {
                return $candidate;
            }
            $suffix++;
        }
    }

    private function touchParent(?int $parentId): void
    {
        if ($parentId !== null) {
            $this->fileRepo->touch($parentId);
        }
    }

    private function detectMimeType(string $path, string $filename): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        if ($mimeType === false || $mimeType === 'application/octet-stream') {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mimeType = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'pdf' => 'application/pdf',
                'json' => 'application/json',
                'xml' => 'application/xml',
                'zip' => 'application/zip',
                'txt', 'log' => 'text/plain',
                'html', 'htm' => 'text/html',
                'css' => 'text/css',
                'js' => 'application/javascript',
                'mp4' => 'video/mp4',
                'mp3' => 'audio/mpeg',
                default => 'application/octet-stream',
            };
        }

        return $mimeType;
    }
}
