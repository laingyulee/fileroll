<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use FileRoll\Database\Connection;
use Sabre\DAV\Auth\Backend\AbstractBasic;

class AuthBackend extends AbstractBasic
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
        $this->setRealm('FileRoll WebDAV');
    }

    protected function validateUserPass($username, $password): bool
    {
        $userData = $this->db->fetch(
            'SELECT id, password_hash, is_active FROM users WHERE username = ?',
            [$username]
        );

        if ($userData === null || !$userData['is_active']) {
            return false;
        }

        if (!password_verify($password, $userData['password_hash'])) {
            return false;
        }

        $_SESSION['webdav_user_id'] = $userData['id'];
        return true;
    }
}
