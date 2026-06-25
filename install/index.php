<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(32));
}
$installToken = $_SESSION['install_token'];

if (!defined('BASE_URL')) {
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    if (str_starts_with($appRoot, $docRoot)) {
        $baseUrl = substr($appRoot, strlen($docRoot));
    } else {
        $baseUrl = '';
    }
    define('BASE_URL', $baseUrl !== '' ? $baseUrl : '');
}

$installedFile = __DIR__ . '/.installed';
if (file_exists($installedFile)) {
    die('Application is already installed. Delete install/.installed to reinstall.');
}

require_once __DIR__ . '/../vendor/autoload.php';

use FileRoll\Core\Config;
use FileRoll\Database\Connection;
use FileRoll\Database\Migration;

$step = $_GET['step'] ?? 'check';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['_token'] ?? '';
    if ($submittedToken === '' || !hash_equals($installToken, $submittedToken)) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $step = $_POST['step'] ?? $step;
        if ($step === 'check') {
        $step = 'database';
    } elseif ($step === 'database') {
        $driver = $_POST['driver'] ?? 'sqlite';
        $_SESSION['install']['driver'] = $driver;

        if ($driver === 'mysql') {
            $_SESSION['install']['mysql'] = [
                'host' => $_POST['mysql_host'] ?? '127.0.0.1',
                'port' => $_POST['mysql_port'] ?? '3306',
                'database' => $_POST['mysql_database'] ?? 'fileroll',
                'username' => $_POST['mysql_username'] ?? 'root',
                'password' => $_POST['mysql_password'] ?? '',
            ];
        }

        $step = 'admin';
    } elseif ($step === 'admin') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            $error = 'All fields are required';
            $step = 'admin';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
            $step = 'admin';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
            $step = 'admin';
        } else {
            $_SESSION['install']['admin'] = [
                'username' => $username,
                'email' => $email,
                'password' => $password,
            ];

            try {
                $rootDir = dirname(__DIR__);
                $configData = [
                    'app' => [
                        'name' => 'FileRoll',
                        'url' => BASE_URL . '/',
                        'debug' => false,
                        'timezone' => 'UTC',
                    ],
                    'database' => [
                        'driver' => $_SESSION['install']['driver'],
                        'sqlite' => [
                            'path' => $rootDir . '/storage/fileroll.db',
                        ],
                        'mysql' => $_SESSION['install']['mysql'] ?? [
                            'host' => '127.0.0.1',
                            'port' => 3306,
                            'database' => 'fileroll',
                            'username' => 'root',
                            'password' => '',
                            'charset' => 'utf8mb4',
                        ],
                    ],
                    'storage' => [
                        'content_path' => $rootDir . '/storage/content',
                        'temp_path' => $rootDir . '/storage/temp',
                        'trash_path' => $rootDir . '/storage/trash',
                        'default_quota' => 107374182400,
                    ],
                    'session' => [
                        'lifetime' => 7200,
                        'name' => 'fileroll_session',
                        'cookie_params' => [
                            'httponly' => true,
                            'secure' => true,
                            'samesite' => 'Strict',
                        ],
                    ],
                    'security' => [
                        'csrf_enabled' => true,
                        'rate_limit_login' => 5,
                        'rate_limit_window' => 900,
                        'max_upload_size' => 5368709120,
                    ],

                    'files' => [
                        'batch_download_max_size' => 100 * 1024 * 1024,
                    ],
                ];

                $configPath = __DIR__ . '/../config/config.php';
                file_put_contents($configPath, '<?php return ' . var_export($configData, true) . ';');

                $config = Config::fromFile($configPath);
                $db = Connection::create($config);
                $migration = new Migration($db);
                $migration->migrate();
                $migration->seedAdmin($username, $email, $password);

                file_put_contents($installedFile, date('Y-m-d H:i:s'));

                $step = 'complete';
                $success = 'Installation completed successfully!';
            } catch (\Throwable $e) {
                $error = 'Installation error: ' . $e->getMessage();
                $step = 'admin';
            }
        }
    }
}
}

$checks = [
    'php_version' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'pdo' => extension_loaded('pdo'),
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'json' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
    'fileinfo' => extension_loaded('fileinfo'),
    'gd' => extension_loaded('gd'),
    'storage_writable' => is_writable(__DIR__ . '/../storage'),
    'config_writable' => is_writable(__DIR__ . '/../config'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install FileRoll</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <style>
    .installer { max-width: 600px; margin: 40px auto; padding: 0 20px; }
    .installer-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 32px; }
    .installer-header { text-align: center; margin-bottom: 24px; }
    .installer-header h1 { color: #4f46e5; }
    .installer-steps { display: flex; justify-content: center; gap: 8px; margin-bottom: 24px; }
    .step { padding: 8px 16px; border-radius: 20px; font-size: 13px; background: #e2e8f0; color: #64748b; }
    .step.active { background: #4f46e5; color: #fff; }
    .step.done { background: #22c55e; color: #fff; }
    .check-list { list-style: none; }
    .check-list li { padding: 8px 0; display: flex; align-items: center; gap: 8px; }
    .check-ok { color: #22c55e; }
    .check-fail { color: #ef4444; }
    .btn-row { display: flex; justify-content: flex-end; margin-top: 20px; }
    </style>
</head>
<body>
<div class="installer">
    <div class="installer-card">
        <div class="installer-header">
            <h1>FileRoll Installer</h1>
            <p>Personal Cloud Storage</p>
        </div>

        <div class="installer-steps">
            <span class="step <?= $step === 'check' ? 'active' : ($step !== 'check' ? 'done' : '') ?>">1. Check</span>
            <span class="step <?= $step === 'database' ? 'active' : ($step === 'admin' || $step === 'complete' ? 'done' : '') ?>">2. Database</span>
            <span class="step <?= $step === 'admin' ? 'active' : ($step === 'complete' ? 'done' : '') ?>">3. Admin</span>
            <span class="step <?= $step === 'complete' ? 'active' : '' ?>">4. Done</span>
        </div>

        <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 'check'): ?>
        <h3>Environment Check</h3>
        <ul class="check-list">
            <li><span class="<?= $checks['php_version'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['php_version'] ? '✓' : '✗' ?></span> PHP <?= PHP_VERSION ?> (requires 8.0+)</li>
            <li><span class="<?= $checks['pdo'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['pdo'] ? '✓' : '✗' ?></span> PDO extension</li>
            <li><span class="<?= $checks['pdo_sqlite'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['pdo_sqlite'] ? '✓' : '✗' ?></span> PDO SQLite driver</li>
            <li><span class="<?= $checks['pdo_mysql'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['pdo_mysql'] ? '✓' : '✗' ?></span> PDO MySQL driver</li>
            <li><span class="<?= $checks['json'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['json'] ? '✓' : '✗' ?></span> JSON extension</li>
            <li><span class="<?= $checks['mbstring'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['mbstring'] ? '✓' : '✗' ?></span> MBString extension</li>
            <li><span class="<?= $checks['fileinfo'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['fileinfo'] ? '✓' : '✗' ?></span> FileInfo extension</li>
            <li><span class="<?= $checks['gd'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['gd'] ? '✓' : '✗' ?></span> GD extension</li>
            <li><span class="<?= $checks['storage_writable'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['storage_writable'] ? '✓' : '✗' ?></span> Storage directory writable</li>
            <li><span class="<?= $checks['config_writable'] ? 'check-ok' : 'check-fail' ?>"><?= $checks['config_writable'] ? '✓' : '✗' ?></span> Config directory writable</li>
        </ul>
        <div class="btn-row">
            <form method="post">
                <input type="hidden" name="_token" value="<?= $installToken ?>">
                <input type="hidden" name="step" value="check">
                <button type="submit" class="btn btn-primary" <?= in_array(false, $checks, true) ? 'disabled' : '' ?>>Continue</button>
            </form>
        </div>

        <?php elseif ($step === 'database'): ?>
        <h3>Database Configuration</h3>
        <form method="post">
            <input type="hidden" name="_token" value="<?= $installToken ?>">
            <input type="hidden" name="step" value="database">
            <div class="form-group">
                <label>Database Driver</label>
                <select name="driver" id="db-driver" onchange="toggleMysqlFields()">
                    <option value="sqlite">SQLite (recommended, no setup needed)</option>
                    <option value="mysql">MySQL</option>
                </select>
            </div>
            <div id="mysql-fields" style="display:none">
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" name="mysql_host" value="127.0.0.1">
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="text" name="mysql_port" value="3306">
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="mysql_database" value="fileroll">
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="mysql_username" value="root">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="mysql_password" value="">
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Continue</button>
            </div>
        </form>
        <script>
        function toggleMysqlFields() {
            document.getElementById('mysql-fields').style.display =
                document.getElementById('db-driver').value === 'mysql' ? 'block' : 'none';
        }
        </script>

        <?php elseif ($step === 'admin'): ?>
        <h3>Create Admin Account</h3>
        <form method="post">
            <input type="hidden" name="_token" value="<?= $installToken ?>">
            <input type="hidden" name="step" value="admin">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="8">
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Complete Installation</button>
            </div>
        </form>

        <?php elseif ($step === 'complete'): ?>
        <div style="text-align:center; padding: 20px 0;">
            <h3 style="color: #22c55e;">Installation Complete!</h3>
            <p style="margin: 12px 0;">FileRoll has been installed successfully.</p>
            <p style="margin: 12px 0;">
                <strong>Important:</strong> Delete the <code>install/</code> directory for security.
            </p>
            <a href="<?= BASE_URL ?>/login" class="btn btn-primary">Go to Login</a>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
