<?php

declare(strict_types=1);

namespace FileRoll\Permission;

class Permission
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly int $fileId = 0,
        public readonly int $userId = 0,
        public readonly string $permissionLevel = 'read',
        public readonly ?int $grantedBy = null,
        public readonly ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            fileId: (int) ($data['file_id'] ?? 0),
            userId: (int) ($data['user_id'] ?? 0),
            permissionLevel: $data['permission_level'] ?? 'read',
            grantedBy: isset($data['granted_by']) ? (int) $data['granted_by'] : null,
            createdAt: $data['created_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->fileId,
            'user_id' => $this->userId,
            'permission_level' => $this->permissionLevel,
            'granted_by' => $this->grantedBy,
            'created_at' => $this->createdAt,
        ];
    }

    public function canRead(): bool
    {
        return in_array($this->permissionLevel, ['read', 'write', 'owner'], true);
    }

    public function canWrite(): bool
    {
        return in_array($this->permissionLevel, ['write', 'owner'], true);
    }

    public function isOwner(): bool
    {
        return $this->permissionLevel === 'owner';
    }
}
