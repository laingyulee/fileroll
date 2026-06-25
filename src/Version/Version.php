<?php

declare(strict_types=1);

namespace FileRoll\Version;

class Version
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly int $fileId = 0,
        public readonly int $versionNumber = 1,
        public readonly string $contentHash = '',
        public readonly string $storagePath = '',
        public readonly int $size = 0,
        public readonly ?int $createdBy = null,
        public readonly ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            fileId: (int) ($data['file_id'] ?? 0),
            versionNumber: (int) ($data['version_number'] ?? 1),
            contentHash: $data['content_hash'] ?? '',
            storagePath: $data['storage_path'] ?? '',
            size: (int) ($data['size'] ?? 0),
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            createdAt: $data['created_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->fileId,
            'version_number' => $this->versionNumber,
            'content_hash' => $this->contentHash,
            'storage_path' => $this->storagePath,
            'size' => $this->size,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
        ];
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
