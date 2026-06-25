<?php

declare(strict_types=1);

namespace FileRoll\User;

use FileRoll\Database\Connection;

class UserRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?User
    {
        $data = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
        return $data ? User::fromArray($data) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $data = $this->db->fetch('SELECT * FROM users WHERE username = ?', [$username]);
        return $data ? User::fromArray($data) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $data = $this->db->fetch('SELECT * FROM users WHERE email = ?', [$email]);
        return $data ? User::fromArray($data) : null;
    }

    public function create(string $username, string $email, string $passwordHash, string $role = 'user', int $quota = 107374182400): User
    {
        $id = $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'display_name' => ucfirst($username),
            'role' => $role,
            'storage_quota' => (string) $quota,
        ]);

        return $this->findById((int) $id);
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['email', 'display_name', 'storage_quota', 'role', 'avatar_path', 'is_active', 'language'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if (empty($filtered)) {
            return false;
        }

        $filtered['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->update('users', $filtered, 'id = ?', [(string) $id]) > 0;
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        return $this->db->update('users', [
            'password_hash' => $passwordHash,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(string) $id]) > 0;
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(string) $id]);
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('users', 'id = ?', [(string) $id]) > 0;
    }

    public function findAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM users ORDER BY created_at DESC');
        return array_map(fn($row) => User::fromArray($row), $rows);
    }

    public function count(): int
    {
        return $this->db->count('users');
    }

    public function countActiveAdmins(): int
    {
        $result = $this->db->fetch(
            'SELECT COUNT(*) as cnt FROM users WHERE role = ? AND is_active = 1',
            ['admin']
        );
        return (int) ($result['cnt'] ?? 0);
    }

    public function getStorageUsed(int $userId): int
    {
        $result = $this->db->fetch(
            'SELECT COALESCE(SUM(size), 0) as total FROM files WHERE owner_id = ? AND is_trashed = 0 AND is_folder = 0',
            [$userId]
        );
        return (int) ($result['total'] ?? 0);
    }

    public function existsByUsername(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM users WHERE username = ?';
        $params = [$username];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        return $this->db->fetch($sql, $params)['cnt'] > 0;
    }

    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM users WHERE email = ?';
        $params = [$email];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        return $this->db->fetch($sql, $params)['cnt'] > 0;
    }
}
