<?php
/** @var \FileRoll\User\User|array $user */
/** @var int $storageUsed */
$storageUsed = $storageUsed ?? 0;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

function u($user, string $key, mixed $default = ''): mixed {
    if ($user instanceof \FileRoll\User\User) {
        return match($key) {
            'username' => $user->username,
            'email' => $user->email,
            'display_name' => $user->displayName,
            'storage_quota' => $user->storageQuota,
            'role' => $user->role,
            default => $default,
        };
    }
    return $user[$key] ?? $default;
}

$quota = u($user, 'storage_quota', 0);
$isUnlimited = $quota <= 0;
$pct = $isUnlimited ? 0 : min(100, $storageUsed / $quota * 100);
$usedGB = $storageUsed / 1073741824;
$quotaGB = $isUnlimited ? 0 : $quota / 1073741824;
?>
<?php ob_start(); ?>
<div class="settings-page">
    <div class="page-header">
        <h2><?= t('settings.profile') ?></h2>
    </div>

    <?php if ($success !== ''): ?>
    <div class="alert alert-success">
        <?php if ($success === 'password'): ?>
            <?= t('settings.password_changed') ?>
        <?php else: ?>
            <?= t('settings.profile_updated') ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="settings-profile-card">
        <div class="settings-avatar">
            <span class="avatar-letter"><?= strtoupper(substr(u($user, 'display_name', u($user, 'username', 'U')), 0, 1)) ?></span>
        </div>
        <div class="settings-profile-info">
            <h3 class="settings-username"><?= htmlspecialchars(u($user, 'display_name', u($user, 'username', 'User'))) ?></h3>
            <p class="settings-email"><?= htmlspecialchars(u($user, 'email')) ?></p>
            <span class="settings-role-badge"><?= ucfirst(u($user, 'role', 'user')) ?></span>
        </div>
    </div>

    <div class="settings-grid">
        <div class="settings-section">
            <div class="settings-section-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <h3><?= t('settings.profile') ?></h3>
            </div>
            <form method="post" action="<?= BASE_URL ?>/settings" class="settings-form">
                <?= $csrf->getTokenField() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username"><?= t('settings.username') ?></label>
                        <input type="text" id="username" value="<?= htmlspecialchars(u($user, 'username')) ?>" disabled>
                        <span class="form-hint"><?= t('settings.username_disabled') ?></span>
                    </div>
                    <div class="form-group">
                        <label for="display_name"><?= t('settings.display_name') ?></label>
                        <input type="text" id="display_name" name="display_name" value="<?= htmlspecialchars(u($user, 'display_name')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="email"><?= t('settings.email') ?></label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars(u($user, 'email')) ?>">
                </div>
                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary"><?= t('settings.save_changes') ?></button>
                </div>
            </form>
        </div>

        <div class="settings-section">
            <div class="settings-section-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <h3><?= t('settings.password_section') ?></h3>
            </div>
            <form method="post" action="<?= BASE_URL ?>/settings/password" class="settings-form">
                <?= $csrf->getTokenField() ?>
                <div class="form-group">
                    <label for="current_password"><?= t('settings.current_password') ?></label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password"><?= t('settings.new_password') ?></label>
                        <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><?= t('settings.confirm_password') ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                    </div>
                </div>
                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary"><?= t('settings.change_password') ?></button>
                </div>
            </form>
        </div>

        <div class="settings-section">
            <div class="settings-section-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                <h3><?= t('settings.storage_section') ?></h3>
            </div>
            <div class="storage-detail-grid">
                <div class="storage-visual">
                    <?php if ($isUnlimited): ?>
                    <div class="storage-unlimited">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                        <span><?= t('settings.unlimited_storage') ?></span>
                    </div>
                    <?php else: ?>
                    <div class="storage-ring">
                        <svg viewBox="0 0 36 36">
                            <path class="storage-ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke-width="3"></path>
                            <path class="storage-ring-fill" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke-width="3" stroke-dasharray="<?= $pct ?>, 100"></path>
                        </svg>
                        <div class="storage-ring-label">
                            <span class="storage-ring-pct"><?= round($pct) ?>%</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="storage-stats">
                    <div class="storage-stat">
                        <span class="storage-stat-value"><?= number_format($usedGB, 2) ?></span>
                        <span class="storage-stat-unit">GB</span>
                        <span class="storage-stat-label"><?= t('settings.used') ?></span>
                    </div>
                    <div class="storage-stat-divider"></div>
                    <div class="storage-stat">
                        <span class="storage-stat-value"><?= $isUnlimited ? '∞' : number_format($quotaGB, 0) ?></span>
                        <span class="storage-stat-unit"><?= $isUnlimited ? '' : 'GB' ?></span>
                        <span class="storage-stat-label"><?= t('settings.total') ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php
$content = ob_get_clean();
$extraScripts = '';
include __DIR__ . '/../base.html.php';
?>
