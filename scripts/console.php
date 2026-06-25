<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FileRoll\Core\Config;
use FileRoll\Database\Connection;
use FileRoll\Database\Migration;
use FileRoll\User\UserRepository;
use FileRoll\Auth\Password;
use FileRoll\File\FileRepository;
use FileRoll\File\FileService;
use FileRoll\File\Storage;
use FileRoll\Version\VersionRepository;
use FileRoll\Settings\SettingsRepository;

$args = $argv;
$command = $args[1] ?? 'help';

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    echo "Error: config/config.php not found. Run the installer first.\n";
    exit(1);
}

$config = Config::fromFile($configPath);
$db = Connection::create($config);

$fn = match (true) {
    $command === 'migrate' => fn() => cmdMigrate($db),
    $command === 'create-user' => fn() => cmdCreateUser($db, $args),
    $command === 'reset-password' => fn() => cmdResetPassword($db, $args),
    $command === 'cleanup-sessions' => fn() => cmdCleanupSessions($db),
    $command === 'cleanup-audit' => fn() => cmdCleanupAudit($db, $args),
    $command === 'cleanup-trash' => fn() => cmdCleanupTrash($db, $config, $args),
    $command === 'storage-stats' => fn() => cmdStorageStats($config),
    $command === 'help' => fn() => cmdHelp(),
    default => fn() => printf("Unknown command: %s\n", $command),
};
$fn();

function cmdMigrate(Connection $db): void
{
    echo "Running migrations...\n";
    $migration = new Migration($db);
    $migration->migrate();
    echo "Migrations completed successfully.\n";
}

function cmdCreateUser(Connection $db, array $args): void
{
    $username = $args[2] ?? readline('Username: ');
    $email = $args[3] ?? readline('Email: ');
    $password = $args[4] ?? readPassword('Password: ');
    $role = $args[5] ?? 'user';

    if ($username === '' || $email === '' || $password === '') {
        echo "Error: All fields are required.\n";
        exit(1);
    }

    $userRepo = new UserRepository($db);
    $passwordAuth = new Password();

    if ($userRepo->existsByUsername($username)) {
        echo "Error: Username already exists.\n";
        exit(1);
    }

    if ($userRepo->existsByEmail($email)) {
        echo "Error: Email already exists.\n";
        exit(1);
    }

    $hash = $passwordAuth->hash($password);
    $user = $userRepo->create($username, $email, $hash, $role);

    echo "User created successfully: {$username} (ID: {$user->id})\n";
}

function cmdResetPassword(Connection $db, array $args): void
{
    $username = $args[2] ?? readline('Username: ');
    $newPassword = $args[3] ?? readPassword('New password: ');

    $userRepo = new UserRepository($db);
    $passwordAuth = new Password();

    $user = $userRepo->findByUsername($username);
    if ($user === null) {
        echo "Error: User not found.\n";
        exit(1);
    }

    $hash = $passwordAuth->hash($newPassword);
    $userRepo->updatePassword($user->id, $hash);

    echo "Password reset successfully for {$username}.\n";
}

function cmdCleanupSessions(Connection $db): void
{
    $deleted = $db->delete('sessions', 'expires_at < ?', [date('Y-m-d H:i:s')]);
    echo "Cleaned up {$deleted} expired sessions.\n";
}

function cmdCleanupAudit(Connection $db, array $args): void
{
    $days = (int) ($args[2] ?? 90);
    $deleted = $db->delete('audit_log', 'created_at < ?', [date('Y-m-d H:i:s', strtotime("-{$days} days"))]);
    echo "Cleaned up {$deleted} audit log entries older than {$days} days.\n";
}

function cmdCleanupTrash(Connection $db, Config $config, array $args): void
{
    $settingsRepo = new SettingsRepository($db);
    $autoClean = $settingsRepo->get('trash_auto_clean', '0');

    if ($autoClean !== '1') {
        echo "Trash auto-clean is disabled. Enable it in admin settings.\n";
        exit(0);
    }

    $graceDays = (int) ($args[2] ?? $settingsRepo->get('trash_grace_days', '30'));
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$graceDays} days"));

    $fileRepo = new FileRepository($db);
    $trashed = $db->fetchAll(
        'SELECT * FROM files WHERE is_trashed = 1 AND trashed_at IS NOT NULL AND trashed_at < ?',
        [$cutoff]
    );

    echo "Found " . count($trashed) . " trashed files older than {$graceDays} days.\n";

    $storage = new Storage($config);
    $versionRepo = new VersionRepository($db);
    $fileService = new FileService($fileRepo, $versionRepo, $storage, $db, $config);
    $deleted = 0;

    foreach ($trashed as $row) {
        $file = FileRoll\File\FileEntity::fromArray($row);
        $result = $fileService->delete($file->id, $file->ownerId);
        if ($result['success']) {
            $deleted++;
        }
    }

    echo "Deleted {$deleted} files permanently.\n";
}

function cmdStorageStats(Config $config): void
{
    $storage = new \FileRoll\File\Storage($config);
    $stats = $storage->getStorageStats();

    echo "Storage Statistics:\n";
    echo "  Total files: {$stats['total_files']}\n";
    echo "  Total size: " . number_format($stats['total_size'] / 1048576, 2) . " MB\n";
    echo "  Location: {$stats['content_path']}\n";
}

function cmdHelp(): void
{
    echo "FileRoll Console\n\n";
    echo "Commands:\n";
    echo "  migrate              Run database migrations\n";
    echo "  create-user          Create a new user\n";
    echo "  reset-password       Reset a user's password\n";
    echo "  cleanup-sessions     Remove expired sessions\n";
    echo "  cleanup-audit [days] Remove old audit log entries (default: 90 days)\n";
    echo "  cleanup-trash [days] Permanently delete old trashed files (uses admin settings or argument)\n";
    echo "  storage-stats        Show storage statistics\n";
    echo "  help                 Show this help\n";
}

function readPassword(string $prompt): string
{
    echo $prompt;

    if (PHP_OS_FAMILY === 'Windows') {
        $password = trim(fgets(STDIN));
        echo "\n";
        return $password;
    }

    $old = shell_exec('stty -g');
    shell_exec('stty -echo');
    try {
        $password = fgets(STDIN);
    } finally {
        shell_exec('stty ' . $old);
        echo "\n";
    }

    return trim($password);
}
