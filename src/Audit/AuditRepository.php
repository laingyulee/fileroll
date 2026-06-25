<?php

declare(strict_types=1);

namespace FileRoll\Audit;

use FileRoll\Database\Connection;

class AuditRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function log(int $userId, string $action, ?string $resourceType = null, ?int $resourceId = null, ?array $details = null, ?string $ip = null, ?string $userAgent = null): void
    {
        $this->db->insert('audit_log', [
            'user_id' => (string) $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId !== null ? (string) $resourceId : null,
            'details' => $details !== null ? json_encode($details) : null,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    public function findByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM audit_log WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$userId, $limit, $offset]
        );
        return array_map(fn($row) => AuditLog::fromArray($row), $rows);
    }

    public function findByResource(string $resourceType, int $resourceId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM audit_log WHERE resource_type = ? AND resource_id = ? ORDER BY created_at DESC',
            [$resourceType, $resourceId]
        );
        return array_map(fn($row) => AuditLog::fromArray($row), $rows);
    }

    public function findRecent(int $limit = 100): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ?',
            [$limit]
        );
        return array_map(fn($row) => AuditLog::fromArray($row), $rows);
    }

    public function deleteByUserId(int $userId): int
    {
        return $this->db->delete('audit_log', 'user_id = ?', [$userId]);
    }

    public function cleanup(int $daysToKeep = 90): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        return $this->db->delete('audit_log', 'created_at < ?', [$cutoff]);
    }
}
