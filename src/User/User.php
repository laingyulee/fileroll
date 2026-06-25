<?php

declare(strict_types=1);

namespace FileRoll\User;

class User
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly string $username = '',
        public readonly string $email = '',
        public readonly string $passwordHash = '',
        public readonly string $displayName = '',
        public readonly int $storageQuota = 107374182400,
        public readonly string $role = 'user',
        public readonly ?string $avatarPath = null,
        public readonly ?string $lastLoginAt = null,
        public readonly bool $isActive = true,
        public readonly string $language = 'en',
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            username: $data['username'] ?? '',
            email: $data['email'] ?? '',
            passwordHash: $data['password_hash'] ?? '',
            displayName: $data['display_name'] ?? '',
            storageQuota: (int) ($data['storage_quota'] ?? 107374182400),
            role: $data['role'] ?? 'user',
            avatarPath: $data['avatar_path'] ?? null,
            lastLoginAt: $data['last_login_at'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            language: $data['language'] ?? 'en',
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'storage_quota' => $this->storageQuota,
            'role' => $this->role,
            'avatar_path' => $this->avatarPath,
            'last_login_at' => $this->lastLoginAt,
            'is_active' => $this->isActive,
            'language' => $this->language,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    public function toSafeArray(): array
    {
        $data = $this->toArray();
        unset($data['password_hash']);
        return $data;
    }
}
