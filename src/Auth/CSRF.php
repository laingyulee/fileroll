<?php

declare(strict_types=1);

namespace FileRoll\Auth;

class CSRF
{
    private string $tokenName = 'csrf_token';

    public function generateToken(): string
    {
        if (empty($_SESSION[$this->tokenName])) {
            $_SESSION[$this->tokenName] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$this->tokenName];
    }

    public function getTokenField(): string
    {
        $token = $this->generateToken();
        return '<input type="hidden" name="' . $this->tokenName . '" value="' . htmlspecialchars($token) . '">';
    }

    public function getTokenMeta(): string
    {
        $token = $this->generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    public function validateToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION[$this->tokenName] ?? null;

        if ($sessionToken === null) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    public function validateRequest(\FileRoll\Core\Request $request): bool
    {
        $token = $request->input($this->tokenName)
            ?? $request->header('X-CSRF-Token');

        return $this->validateToken($token);
    }

    public function clearToken(): void
    {
        unset($_SESSION[$this->tokenName]);
    }
}
