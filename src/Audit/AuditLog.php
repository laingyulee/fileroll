<?php

declare(strict_types=1);

namespace FileRoll\Audit;

class AuditLog
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $userId = null,
        public readonly string $action = '',
        public readonly ?string $resourceType = null,
        public readonly ?int $resourceId = null,
        public readonly ?string $details = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            action: $data['action'] ?? '',
            resourceType: $data['resource_type'] ?? null,
            resourceId: isset($data['resource_id']) ? (int) $data['resource_id'] : null,
            details: $data['details'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            createdAt: $data['created_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'action' => $this->action,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'details' => $this->details,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'created_at' => $this->createdAt,
        ];
    }
}
