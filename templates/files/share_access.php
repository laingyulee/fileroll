<?php
/** @var \FileRoll\Share\Share $share */
/** @var \FileRoll\File\FileEntity $file */
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
    <title><?= htmlspecialchars($file->name) ?> - <?= htmlspecialchars($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/public/assets/img/favicon.svg">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/app.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-logo"><?= htmlspecialchars($siteName) ?></h1>
                <p class="auth-subtitle"><?= t('share_access.shared_file') ?></p>
            </div>
            <div class="share-info">
                <div class="share-file-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                </div>
                <h2><?= htmlspecialchars($file->name) ?></h2>
                <p class="share-meta"><?= $file->getFormattedSize() ?></p>
                <?php if ($share->expiresAt): ?>
                <p class="share-meta"><?= t('share_access.expires') ?> <?= date('M j, Y H:i', strtotime($share->expiresAt)) ?></p>
                <?php endif; ?>
                <?php if ($share->maxDownloads !== null): ?>
                <p class="share-meta"><?= t('share_access.downloads') ?> <?= $share->downloadCount ?> / <?= $share->maxDownloads ?></p>
                <?php endif; ?>
            </div>
            <div class="share-actions">
                <a href="<?= BASE_URL ?>/s/<?= htmlspecialchars($share->token) ?>/download" class="btn btn-primary btn-block"><?= t('files.download') ?></a>
            </div>
        </div>
    </div>
</body>
</html>
