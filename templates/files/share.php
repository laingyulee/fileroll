<?php
$error = $error ?? '';
$siteName = 'FileRoll';
try {
    $db = \FileRoll\Core\Container::getInstance()->get(\FileRoll\Database\Connection::class);
    $settingsRepo = new \FileRoll\Settings\SettingsRepository($db);
    $siteName = $settingsRepo->get('site_name', 'FileRoll');
} catch (\Throwable $e) { error_log('Failed to load site name: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared File - <?= htmlspecialchars($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/public/assets/img/favicon.svg">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/app.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-logo"><?= htmlspecialchars($siteName) ?></h1>
                <p class="auth-subtitle"><?= t('share_access.protected') ?></p>
            </div>
            <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="password"><?= t('auth.password') ?></label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><?= t('share_access.access_file') ?></button>
            </form>
        </div>
    </div>
</body>
</html>
