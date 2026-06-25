<?php

declare(strict_types=1);

namespace FileRoll\File;

class FileEntity
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $parentId = null,
        public readonly string $name = '',
        public readonly ?string $mimeType = null,
        public readonly int $size = 0,
        public readonly bool $isFolder = false,
        public readonly ?string $contentHash = null,
        public readonly ?string $storagePath = null,
        public readonly bool $isStarred = false,
        public readonly bool $isTrashed = false,
        public readonly ?string $trashedAt = null,
        public readonly int $ownerId = 0,
        public readonly ?int $createdBy = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            name: $data['name'] ?? '',
            mimeType: $data['mime_type'] ?? null,
            size: (int) ($data['size'] ?? 0),
            isFolder: (bool) ($data['is_folder'] ?? false),
            contentHash: $data['content_hash'] ?? null,
            storagePath: $data['storage_path'] ?? null,
            isStarred: (bool) ($data['is_starred'] ?? false),
            isTrashed: (bool) ($data['is_trashed'] ?? false),
            trashedAt: $data['trashed_at'] ?? null,
            ownerId: (int) ($data['owner_id'] ?? 0),
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'is_folder' => $this->isFolder,
            'content_hash' => $this->contentHash,
            'storage_path' => $this->storagePath,
            'is_starred' => $this->isStarred,
            'is_trashed' => $this->isTrashed,
            'trashed_at' => $this->trashedAt,
            'owner_id' => $this->ownerId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function getExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }

    public function getIcon(): string
    {
        if ($this->isFolder) return 'folder';

        $ext = strtolower($this->getExtension());
        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp']) => 'image',
            in_array($ext, ['mp4', 'webm', 'avi', 'mov', 'mkv']) => 'video',
            in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'aac']) => 'audio',
            in_array($ext, ['pdf']) => 'pdf',
            in_array($ext, ['doc', 'docx']) => 'word',
            in_array($ext, ['xls', 'xlsx']) => 'excel',
            in_array($ext, ['ppt', 'pptx']) => 'powerpoint',
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']) => 'archive',
            in_array($ext, ['txt', 'md', 'log']) => 'text',
            in_array($ext, ['php', 'js', 'css', 'html', 'py', 'java', 'c', 'cpp', 'rb']) => 'code',
            default => 'file',
        };
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType ?? '', 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mimeType ?? '', 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mimeType ?? '', 'audio/');
    }

    public function isText(): bool
    {
        return str_starts_with($this->mimeType ?? '', 'text/')
            || in_array($this->mimeType, ['application/json', 'application/javascript', 'application/xml'], true);
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    public function getFormattedSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
