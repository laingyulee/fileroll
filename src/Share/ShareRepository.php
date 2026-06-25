<?php

declare(strict_types=1);

namespace FileRoll\Share;

use FileRoll\Database\Connection;

class ShareRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?Share
    {
        $data = $this->db->fetch('SELECT * FROM shares WHERE id = ?', [$id]);
        return $data ? Share::fromArray($data) : null;
    }

    public function findByToken(string $token): ?Share
    {
        $data = $this->db->fetch('SELECT * FROM shares WHERE token = ? AND is_active = 1', [$token]);
        return $data ? Share::fromArray($data) : null;
    }

    public function findByFileId(int $fileId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM shares WHERE file_id = ? ORDER BY created_at DESC',
            [$fileId]
        );
        return array_map(fn($row) => Share::fromArray($row), $rows);
    }

    public function findByOwnerId(int $ownerId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT s.* FROM shares s
             JOIN files f ON s.file_id = f.id
             WHERE f.owner_id = ? ORDER BY s.created_at DESC',
            [$ownerId]
        );
        return array_map(fn($row) => Share::fromArray($row), $rows);
    }

    public function create(array $data): Share
    {
        $data['token'] = $data['token'] ?? bin2hex(random_bytes(32));
        $id = $this->db->insert('shares', $data);
        return $this->findById((int) $id);
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->update('shares', $data, 'id = ?', [$id]) > 0;
    }

    public function incrementDownloadCount(int $id): void
    {
        $this->db->query(
            'UPDATE shares SET download_count = download_count + 1 WHERE id = ?',
            [$id]
        );
    }

    public function deactivate(int $id): bool
    {
        return $this->db->update('shares', ['is_active' => 0], 'id = ?', [$id]) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('shares', 'id = ?', [$id]) > 0;
    }

    public function deleteByFileId(int $fileId): bool
    {
        return $this->db->delete('shares', 'file_id = ?', [$fileId]) >= 0;
    }

    public function cleanupExpired(): int
    {
        return $this->db->delete('shares', 'expires_at IS NOT NULL AND expires_at < ?', [date('Y-m-d H:i:s')]);
    }
}
