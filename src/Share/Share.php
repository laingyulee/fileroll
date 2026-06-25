<?php

declare(strict_types=1);

namespace FileRoll\Share;

class Share
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly int $fileId = 0,
        public readonly int $sharedBy = 0,
        public readonly ?int $sharedWith = null,
        public readonly string $token = '',
        public readonly string $permissionLevel = 'read',
        public readonly ?string $passwordHash = null,
        public readonly ?string $expiresAt = null,
        public readonly ?int $maxDownloads = null,
        public readonly int $downloadCount = 0,
        public readonly bool $isActive = true,
        public readonly ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            fileId: (int) ($data['file_id'] ?? 0),
            sharedBy: (int) ($data['shared_by'] ?? 0),
            sharedWith: isset($data['shared_with']) ? (int) $data['shared_with'] : null,
            token: $data['token'] ?? '',
            permissionLevel: $data['permission_level'] ?? 'read',
            passwordHash: $data['password_hash'] ?? null,
            expiresAt: $data['expires_at'] ?? null,
            maxDownloads: isset($data['max_downloads']) ? (int) $data['max_downloads'] : null,
            downloadCount: (int) ($data['download_count'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? true),
            createdAt: $data['created_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->fileId,
            'shared_by' => $this->sharedBy,
            'shared_with' => $this->sharedWith,
            'token' => $this->token,
            'permission_level' => $this->permissionLevel,
            'has_password' => $this->passwordHash !== null,
            'expires_at' => $this->expiresAt,
            'max_downloads' => $this->maxDownloads,
            'download_count' => $this->downloadCount,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
        ];
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        return strtotime($this->expiresAt) < time();
    }

    public function isDownloadLimitReached(): bool
    {
        if ($this->maxDownloads === null) {
            return false;
        }
        return $this->downloadCount >= $this->maxDownloads;
    }

    public function isValid(): bool
    {
        return $this->isActive && !$this->isExpired() && !$this->isDownloadLimitReached();
    }

    public function hasPassword(): bool
    {
        return $this->passwordHash !== null;
    }

    public function getShareUrl(): string
    {
        return '/s/' . $this->token;
    }
}
