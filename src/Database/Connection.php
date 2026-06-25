<?php

declare(strict_types=1);

namespace FileRoll\Database;

use FileRoll\Core\Config;
use PDO;

class Connection
{
    private PDO $pdo;
    private string $driver;

    private function __construct(PDO $pdo, string $driver)
    {
        $this->pdo = $pdo;
        $this->driver = $driver;
    }

    public static function create(Config $config): self
    {
        $driver = $config->get('database.driver', 'sqlite');
        $pdo = match ($driver) {
            'sqlite' => self::createSqlite($config),
            'mysql' => self::createMysql($config),
            default => throw new \InvalidArgumentException("Unsupported database driver: {$driver}"),
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        if ($driver === 'mysql') {
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        return new self($pdo, $driver);
    }

    private static function createSqlite(Config $config): PDO
    {
        $path = $config->get('database.sqlite.path', __DIR__ . '/../../storage/fileroll.db');
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new PDO("sqlite:{$path}");
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA busy_timeout=5000');
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA synchronous=NORMAL');

        return $pdo;
    }

    private static function createMysql(Config $config): PDO
    {
        $host = $config->get('database.mysql.host', '127.0.0.1');
        $port = $config->get('database.mysql.port', 3306);
        $database = $config->get('database.mysql.database', 'fileroll');
        $username = $config->get('database.mysql.username', 'root');
        $password = $config->get('database.mysql.password', '');
        $charset = $config->get('database.mysql.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function isMysql(): bool
    {
        return $this->driver === 'mysql';
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));

        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE {$where}";
        $result = $this->fetch($sql, $params);
        return (int)($result['cnt'] ?? 0);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function escapeIdentifier(string $identifier): string
    {
        return $this->isMysql()
            ? "`{$identifier}`"
            : "\"{$identifier}\"";
    }
}
