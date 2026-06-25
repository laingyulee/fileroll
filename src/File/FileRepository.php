<?php

declare(strict_types=1);

namespace FileRoll\File;

use FileRoll\Database\Connection;

class FileRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?FileEntity
    {
        $data = $this->db->fetch('SELECT * FROM files WHERE id = ?', [$id]);
        return $data ? FileEntity::fromArray($data) : null;
    }

    public function findByIdAndOwner(int $id, int $ownerId): ?FileEntity
    {
        $data = $this->db->fetch(
            'SELECT * FROM files WHERE id = ? AND owner_id = ? AND is_trashed = 0',
            [$id, $ownerId]
        );
        return $data ? FileEntity::fromArray($data) : null;
    }

    public function findByIdAndOwnerAny(int $id, int $ownerId): ?FileEntity
    {
        $data = $this->db->fetch(
            'SELECT * FROM files WHERE id = ? AND owner_id = ?',
            [$id, $ownerId]
        );
        return $data ? FileEntity::fromArray($data) : null;
    }

    public function findByNameAndParent(string $name, ?int $parentId, int $ownerId): ?FileEntity
    {
        $sql = 'SELECT * FROM files WHERE name = ? AND owner_id = ? AND is_trashed = 0';
        $params = [$name, $ownerId];

        if ($parentId === null) {
            $sql .= ' AND parent_id IS NULL';
        } else {
            $sql .= ' AND parent_id = ?';
            $params[] = $parentId;
        }

        $data = $this->db->fetch($sql, $params);
        return $data ? FileEntity::fromArray($data) : null;
    }

    public function listByParent(?int $parentId, int $ownerId, string $orderBy = 'name', string $direction = 'ASC'): array
    {
        $sql = 'SELECT * FROM files WHERE owner_id = ? AND is_trashed = 0';
        $params = [$ownerId];

        if ($parentId === null) {
            $sql .= ' AND parent_id IS NULL';
        } else {
            $sql .= ' AND parent_id = ?';
            $params[] = $parentId;
        }

        $allowedOrder = ['name', 'size', 'created_at', 'updated_at', 'mime_type'];
        $orderBy = in_array($orderBy, $allowedOrder, true) ? $orderBy : 'name';
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $sql .= " ORDER BY is_folder DESC, {$orderBy} {$direction}";

        $rows = $this->db->fetchAll($sql, $params);
        return array_map(fn($row) => FileEntity::fromArray($row), $rows);
    }

    public function listTrashed(int $ownerId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM files WHERE owner_id = ? AND is_trashed = 1 ORDER BY trashed_at DESC',
            [$ownerId]
        );
        return array_map(fn($row) => FileEntity::fromArray($row), $rows);
    }

    public function listStarred(int $ownerId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM files WHERE owner_id = ? AND is_starred = 1 AND is_trashed = 0 ORDER BY name',
            [$ownerId]
        );
        return array_map(fn($row) => FileEntity::fromArray($row), $rows);
    }

    public function listByParentWithExtensions(?int $parentId, int $ownerId, array $extensions): array
    {
        if (empty($extensions)) {
            return [];
        }

        $sql = 'SELECT * FROM files WHERE owner_id = ? AND is_trashed = 0 AND is_folder = 0';
        $params = [$ownerId];

        $conditions = [];
        foreach ($extensions as $ext) {
            $conditions[] = 'LOWER(name) LIKE ?';
            $params[] = '%.' . strtolower($ext);
        }
        $sql .= ' AND (' . implode(' OR ', $conditions) . ')';

        if ($parentId !== null) {
            $sql .= ' AND parent_id = ?';
            $params[] = $parentId;
        } else {
            $sql .= ' AND parent_id IS NULL';
        }

        $sql .= ' ORDER BY name';

        $rows = $this->db->fetchAll($sql, $params);
        return array_map(fn($row) => FileEntity::fromArray($row), $rows);
    }

    public function search(string $query, int $ownerId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM files WHERE owner_id = ? AND is_trashed = 0 AND name LIKE ? ORDER BY name LIMIT 100',
            [$ownerId, "%{$query}%"]
        );
        return array_map(fn($row) => FileEntity::fromArray($row), $rows);
    }

    public function create(array $data): FileEntity
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $id = $this->db->insert('files', $data);
        return $this->findById((int) $id);
    }

    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('files', $data, 'id = ?', [$id]) > 0;
    }

    public function trash(int $id): bool
    {
        return $this->db->update('files', [
            'is_trashed' => 1,
            'trashed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]) > 0;
    }

    public function restore(int $id): bool
    {
        return $this->db->update('files', [
            'is_trashed' => 0,
            'trashed_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]) > 0;
    }

    public function touch(int $id): bool
    {
        return $this->db->update('files', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('files', 'id = ?', [$id]) > 0;
    }

    public function deletePermanently(int $id): bool
    {
        return $this->db->delete('files', 'id = ? AND is_trashed = 1', [$id]) > 0;
    }

    public function countByOwner(int $ownerId): int
    {
        return $this->db->count('files', 'owner_id = ? AND is_trashed = 0', [$ownerId]);
    }

    public function getStorageUsed(int $ownerId): int
    {
        $result = $this->db->fetch(
            'SELECT COALESCE(SUM(size), 0) as total FROM files WHERE owner_id = ? AND is_trashed = 0 AND is_folder = 0',
            [$ownerId]
        );
        return (int) ($result['total'] ?? 0);
    }

    public function getPath(int $fileId): array
    {
        $rows = $this->db->fetchAll(
            'WITH RECURSIVE ancestors(id) AS (
                SELECT id FROM files WHERE id = ?
                UNION ALL
                SELECT f.id FROM files f JOIN ancestors a ON f.id = (SELECT parent_id FROM files WHERE id = a.id)
            )
            SELECT * FROM files WHERE id IN (SELECT id FROM ancestors) ORDER BY id',
            [$fileId]
        );
        $files = array_map(fn($row) => FileEntity::fromArray($row), $rows);

        $ordered = [];
        $currentId = $fileId;
        while ($currentId !== null) {
            foreach ($files as $file) {
                if ($file->id === $currentId) {
                    $ordered[] = $file;
                    $currentId = $file->parentId;
                    break;
                }
            }
            if (end($ordered)?->id !== $currentId || $currentId === null) {
                break;
            }
        }

        return array_reverse($ordered);
    }

    public function moveAllInParent(?int $parentId, int $ownerId, int $newOwnerId): int
    {
        $sql = 'UPDATE files SET owner_id = ?, updated_at = ? WHERE owner_id = ?';
        $params = [$newOwnerId, date('Y-m-d H:i:s'), $ownerId];

        if ($parentId === null) {
            $sql .= ' AND parent_id IS NULL';
        } else {
            $sql .= ' AND parent_id = ?';
            $params[] = $parentId;
        }

        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
}
