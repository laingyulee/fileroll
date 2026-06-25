<?php

declare(strict_types=1);

namespace FileRoll\Database;

class Migration
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function migrate(): void
    {
        $schemaFile = __DIR__ . '/../../config/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new \RuntimeException("Schema file not found: {$schemaFile}");
        }

        $sql = file_get_contents($schemaFile);
        $statements = $this->parseStatements($sql);

        $this->db->beginTransaction();

        try {
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement !== '' && !str_starts_with($statement, '--')) {
                    $this->db->getPdo()->exec($statement);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function seedAdmin(string $username, string $email, string $password): string
    {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        return $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'display_name' => ucfirst($username),
            'role' => 'admin',
            'storage_quota' => 107374182400,
        ]);
    }

    public function isInstalled(): bool
    {
        try {
            $this->db->query("SELECT 1 FROM users LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function parseStatements(string $sql): array
    {
        $sql = preg_replace('/--.*$/m', '', $sql);
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => $s !== ''
        );
        return $statements;
    }
}
