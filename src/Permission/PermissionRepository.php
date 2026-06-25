<?php

declare(strict_types=1);

namespace FileRoll\Permission;

use FileRoll\Database\Connection;

class PermissionRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findByFileAndUser(int $fileId, int $userId): ?Permission
    {
        $data = $this->db->fetch(
            'SELECT * FROM permissions WHERE file_id = ? AND user_id = ?',
            [$fileId, $userId]
        );
        return $data ? Permission::fromArray($data) : null;
    }

    public function findByFileId(int $fileId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM permissions WHERE file_id = ?',
            [$fileId]
        );
        return array_map(fn($row) => Permission::fromArray($row), $rows);
    }

    public function findByUserId(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM permissions WHERE user_id = ?',
            [$userId]
        );
        return array_map(fn($row) => Permission::fromArray($row), $rows);
    }

    public function create(array $data): Permission
    {
        $existing = $this->findByFileAndUser((int) $data['file_id'], (int) $data['user_id']);
        if ($existing !== null) {
            $this->update($existing->id, ['permission_level' => $data['permission_level']]);
            return $this->findByFileAndUser((int) $data['file_id'], (int) $data['user_id']);
        }

        $id = $this->db->insert('permissions', $data);
        return $this->findByFileAndUser((int) $data['file_id'], (int) $data['user_id']);
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->update('permissions', $data, 'id = ?', [$id]) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('permissions', 'id = ?', [$id]) > 0;
    }

    public function deleteByFileAndUser(int $fileId, int $userId): bool
    {
        return $this->db->delete('permissions', 'file_id = ? AND user_id = ?', [$fileId, $userId]) >= 0;
    }

    public function deleteByFileId(int $fileId): bool
    {
        return $this->db->delete('permissions', 'file_id = ?', [$fileId]) >= 0;
    }

    public function hasPermission(int $fileId, int $userId, string $level = 'read'): bool
    {
        $permission = $this->findByFileAndUser($fileId, $userId);
        if ($permission === null) {
            return false;
        }

        return match ($level) {
            'read' => $permission->canRead(),
            'write' => $permission->canWrite(),
            'owner' => $permission->isOwner(),
            default => false,
        };
    }
}
