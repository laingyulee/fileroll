<?php

declare(strict_types=1);

namespace FileRoll\Version;

use FileRoll\Database\Connection;

class VersionRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findByFileId(int $fileId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM file_versions WHERE file_id = ? ORDER BY version_number DESC',
            [$fileId]
        );
        return array_map(fn($row) => Version::fromArray($row), $rows);
    }

    public function findById(int $id): ?Version
    {
        $data = $this->db->fetch('SELECT * FROM file_versions WHERE id = ?', [$id]);
        return $data ? Version::fromArray($data) : null;
    }

    public function findByVersionNumber(int $fileId, int $versionNumber): ?Version
    {
        $data = $this->db->fetch(
            'SELECT * FROM file_versions WHERE file_id = ? AND version_number = ?',
            [$fileId, $versionNumber]
        );
        return $data ? Version::fromArray($data) : null;
    }

    public function getLatestVersionNumber(int $fileId): int
    {
        $result = $this->db->fetch(
            'SELECT COALESCE(MAX(version_number), 0) as max_ver FROM file_versions WHERE file_id = ?',
            [$fileId]
        );
        return (int) ($result['max_ver'] ?? 0);
    }

    public function create(array $data): Version
    {
        $id = $this->db->insert('file_versions', $data);
        return $this->findById((int) $id);
    }

    public function deleteByFileId(int $fileId): bool
    {
        return $this->db->delete('file_versions', 'file_id = ?', [$fileId]) >= 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('file_versions', 'id = ?', [$id]) > 0;
    }

    public function countByFileId(int $fileId): int
    {
        return $this->db->count('file_versions', 'file_id = ?', [$fileId]);
    }
}
