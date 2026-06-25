<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use FileRoll\Database\Connection;
use Sabre\DAV\Locks;

class LockBackend implements Locks\Backend\BackendInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function getLocks($uri, $returnChildLocks): array
    {
        $locks = $this->db->fetchAll(
            'SELECT * FROM webdav_locks WHERE uri = ? AND expires > ?',
            [$uri, time()]
        );

        return array_map(function ($lock) {
            $info = new Locks\LockInfo();
            $info->uri = $lock['uri'];
            $info->token = $lock['token'];
            $info->created = (int) $lock['created'];
            $info->expires = (int) $lock['expires'];
            $info->owner = $lock['owner'] ?? '';
            $info->depth = (int) ($lock['depth'] ?? 0);
            return $info;
        }, $locks);
    }

    public function lock($uri, Locks\LockInfo $lockInfo): bool
    {
        $this->db->insert('webdav_locks', [
            'uri' => $lockInfo->uri,
            'token' => $lockInfo->token,
            'created' => (string) $lockInfo->created,
            'expires' => (string) $lockInfo->expires,
            'owner' => $lockInfo->owner ?? '',
            'depth' => (string) ($lockInfo->depth ?? 0),
        ]);
        return true;
    }

    public function unlock($uri, Locks\LockInfo $lockInfo): bool
    {
        $this->db->delete('webdav_locks', 'token = ?', [$lockInfo->token]);
        return true;
    }
}
