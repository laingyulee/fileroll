<?php

declare(strict_types=1);

namespace FileRoll\Auth;

use FileRoll\Core\Config;
use FileRoll\Database\Connection;

class Session
{
    private Connection $db;
    private Config $config;

    public function __construct(Connection $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function getClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function start(int $userId, string $ip, string $userAgent): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $lifetime = $this->config->get('session.lifetime', 7200);
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        $this->db->insert('sessions', [
            'id' => $sessionId,
            'user_id' => (string) $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt,
        ]);

        $_SESSION['user_id'] = $userId;
        $_SESSION['session_id'] = $sessionId;

        session_regenerate_id(true);

        return $sessionId;
    }

    public function validate(): ?int
    {
        $userId = $_SESSION['user_id'] ?? null;
        $sessionId = $_SESSION['session_id'] ?? null;

        if ($userId === null || $sessionId === null) {
            return null;
        }

        $session = $this->db->fetch(
            'SELECT * FROM sessions WHERE id = ? AND user_id = ? AND expires_at > ?',
            [$sessionId, $userId, date('Y-m-d H:i:s')]
        );

        if ($session === null) {
            $this->destroy();
            return null;
        }

        $clientIp = $this->getClientIp();
        if ($session['ip_address'] !== null && $session['ip_address'] !== $clientIp) {
            $this->destroy();
            return null;
        }

        $lifetime = $this->config->get('session.lifetime', 7200);
        $expiresAt = strtotime($session['expires_at']);
        $remaining = $expiresAt - time();

        if ($remaining < $lifetime / 2) {
            $newExpiresAt = date('Y-m-d H:i:s', time() + $lifetime);
            $this->db->update('sessions', ['expires_at' => $newExpiresAt], 'id = ?', [$sessionId]);
            $this->refreshSessionCookie($lifetime);
        }

        return (int) $userId;
    }

    public function destroy(): void
    {
        $sessionId = $_SESSION['session_id'] ?? null;

        if ($sessionId !== null) {
            $this->db->delete('sessions', 'id = ?', [$sessionId]);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    private function refreshSessionCookie(int $lifetime): void
    {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            time() + $lifetime,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    public function cleanup(): int
    {
        return $this->db->delete('sessions', 'expires_at < ?', [date('Y-m-d H:i:s')]);
    }

    public function getActiveSessions(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sessions WHERE user_id = ? AND expires_at > ? ORDER BY created_at DESC',
            [$userId, date('Y-m-d H:i:s')]
        );
    }

    public function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function isLoggedIn(): bool
    {
        return $this->getUserId() !== null;
    }
}
