<?php
/** @var array $settings */
$settings = $settings ?? [];
/** @var array $users */
$users = $users ?? [];
$currentUserId = $currentUserId ?? 1;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$siteName = $settings['site_name'] ?? 'FileRoll';
$trashAutoClean = $settings['trash_auto_clean'] ?? '0';
$trashGraceDays = $settings['trash_grace_days'] ?? '30';
$sessionLifetime = (int) ($settings['session_lifetime'] ?? 7200);
$lifetimeOptions = [
    7200 => t('admin.session_lifetime_2h'),
    86400 => t('admin.session_lifetime_1d'),
    2592000 => t('admin.session_lifetime_30d'),
    31536000 => t('admin.session_lifetime_1y'),
    3153600000 => t('admin.session_lifetime_forever'),
];
$activeTab = $_GET['tab'] ?? 'site';
?>
<?php ob_start(); ?>
<div class="page-header">
    <h2><?= t('admin.title') ?></h2>
</div>

<?php if ($success !== ''): ?>
<div class="alert alert-success">
    <?php if ($success === 'deleted'): ?><?= t('admin.user_deleted') ?>
    <?php elseif ($success === 'updated'): ?><?= t('admin.user_updated') ?>
    <?php elseif ($success === 'user-created'): ?><?= t('admin.user_created') ?>
    <?php else: ?><?= t('admin.settings_saved') ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-tabs">
    <button class="admin-tab <?= $activeTab === 'site' ? 'active' : '' ?>" onclick="switchTab('site')"><?= t('admin.site_settings') ?></button>
    <button class="admin-tab <?= $activeTab === 'users' ? 'active' : '' ?>" onclick="switchTab('users')"><?= t('admin.users') ?></button>
</div>

<div id="tab-site" class="admin-tab-panel <?= $activeTab !== 'site' ? 'hidden' : '' ?>">
    <div style="max-width: 600px;">
        <div class="form-group">
            <label for="site-name"><?= t('admin.site_name') ?></label>
            <input type="text" id="site-name" value="<?= htmlspecialchars($siteName) ?>" placeholder="My File Cloud">
            <span class="form-hint"><?= t('admin.site_name_hint') ?></span>
        </div>
        <div class="form-group" style="margin-top:24px;">
            <label class="toggle-label">
                <input type="checkbox" id="trash-auto-clean" value="1" <?= $trashAutoClean === '1' ? 'checked' : '' ?>>
                <span class="toggle-switch"></span>
                <span><?= t('admin.trash_auto_clean') ?></span>
            </label>
            <span class="form-hint"><?= t('admin.trash_auto_clean_hint') ?></span>
        </div>
        <div class="form-group" id="trash-grace-group" style="<?= $trashAutoClean !== '1' ? 'opacity:0.5;pointer-events:none;' : '' ?>">
            <label for="trash-grace-days"><?= t('admin.trash_grace_days') ?></label>
            <input type="number" id="trash-grace-days" min="1" max="365" value="<?= htmlspecialchars($trashGraceDays) ?>">
            <span class="form-hint"><?= t('admin.trash_grace_days_hint') ?></span>
        </div>
        <div class="form-group">
            <label for="session-lifetime"><?= t('admin.session_lifetime') ?></label>
            <select id="session-lifetime" class="form-control">
                <?php foreach ($lifetimeOptions as $value => $label): ?>
                <option value="<?= $value ?>" <?= $value === $sessionLifetime ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-top: 16px;">
            <button class="btn btn-primary" onclick="saveSettings()"><?= t('admin.save_settings') ?></button>
        </div>
    </div>
</div>

<div id="tab-users" class="admin-tab-panel <?= $activeTab !== 'users' ? 'hidden' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <span></span>
        <button class="btn btn-primary" onclick="openModal('create-user-modal')"><?= t('admin.add_user') ?></button>
    </div>

    <table class="file-table">
        <thead>
            <tr>
                <th><?= t('admin.username') ?></th>
                <th><?= t('admin.email') ?></th>
                <th><?= t('admin.role') ?></th>
                <th><?= t('admin.quota') ?></th>
                <th><?= t('admin.status') ?></th>
                <th><?= t('admin.actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u->username) ?> <?= $u->id === $currentUserId ? '<span class="badge-you">' . t('admin.you') . '</span>' : '' ?></td>
                <td><?= htmlspecialchars($u->email) ?></td>
                <td>
                    <select class="role-select" onchange="updateUserRole(<?= $u->id ?>, this.value)" <?= $u->id === $currentUserId ? 'disabled' : '' ?>>
                        <option value="admin" <?= $u->role === 'admin' ? 'selected' : '' ?>><?= t('admin.role_admin') ?></option>
                        <option value="user" <?= $u->role === 'user' ? 'selected' : '' ?>><?= t('admin.role_user') ?></option>
                        <option value="viewer" <?= $u->role === 'viewer' ? 'selected' : '' ?>><?= t('admin.role_viewer') ?></option>
                    </select>
                </td>
                <td>
                    <input type="number" class="quota-input" min="0" value="<?= $u->storageQuota > 0 ? round($u->storageQuota / 1073741824) : 0 ?>" onchange="updateUserQuota(<?= $u->id ?>, this.value)" <?= $u->id === $currentUserId ? 'disabled' : '' ?>>
                    <span class="quota-unit">GB</span>
                </td>
                <td><span class="status-dot <?= $u->isActive ? 'active' : 'disabled' ?>"></span><?= $u->isActive ? t('admin.active') : t('admin.disabled') ?></td>
                <td>
                    <?php if ($u->id !== $currentUserId): ?>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $u->id ?>)"><?= t('files.delete') ?></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-tertiary);padding:32px;"><?= t('admin.no_users') ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="create-user-modal" class="modal hidden">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><?= t('admin.create_user') ?></h3>
            <button class="modal-close" onclick="closeModal('create-user-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="create-user-form" onsubmit="return false;">
                <div class="form-group">
                    <label for="new-username"><?= t('admin.username') ?></label>
                    <input type="text" id="new-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="new-email"><?= t('admin.email') ?></label>
                    <input type="email" id="new-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="new-password"><?= t('auth.password') ?></label>
                    <input type="password" id="new-password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="new-role"><?= t('admin.role') ?></label>
                    <select id="new-role" name="role">
                        <option value="user"><?= t('admin.role_user') ?></option>
                        <option value="admin"><?= t('admin.role_admin') ?></option>
                        <option value="viewer"><?= t('admin.role_viewer') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new-quota"><?= t('admin.quota_label') ?></label>
                    <input type="number" id="new-quota" name="quota" value="10" min="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-user-modal')"><?= t('files.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" onclick="submitCreateUser()"><?= t('admin.create_user') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
const CSRF = '<?= $csrf->generateToken() ?>';

function switchTab(tab) {
    document.querySelectorAll('.admin-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.admin-tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelector('.admin-tab[onclick*="' + tab + '"]').classList.add('active');
    document.getElementById('tab-' + tab).classList.remove('hidden');
    history.replaceState(null, '', BASE + '/admin/settings?tab=' + tab);
}

function saveSettings() {
    var siteName = document.getElementById('site-name').value.trim();
    var trashAutoClean = document.getElementById('trash-auto-clean').checked ? '1' : '0';
    var trashGraceDays = document.getElementById('trash-grace-days').value.trim() || '30';
    var sessionLifetime = parseInt(document.getElementById('session-lifetime').value, 10);
    fetch(BASE + '/admin/settings', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
        body: JSON.stringify({settings: {
            site_name: siteName,
            trash_auto_clean: trashAutoClean,
            trash_grace_days: trashGraceDays,
            session_lifetime: sessionLifetime
        }})
    }).then(guardAuth).then(r => r.json()).then(data => {
        if (data.success) {
            showToast('<?= t('admin.settings_saved') ?>', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('<?= t('admin.settings_save_failed') ?>', 'error');
        }
    }).catch(() => showToast('<?= t('admin.settings_save_failed') ?>', 'error'));
}

document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('trash-auto-clean');
    if (toggle) {
        toggle.addEventListener('change', function() {
            var group = document.getElementById('trash-grace-group');
            if (this.checked) {
                group.style.opacity = '1';
                group.style.pointerEvents = 'auto';
            } else {
                group.style.opacity = '0.5';
                group.style.pointerEvents = 'none';
            }
        });
    }
});

async function deleteUser(id) {
    if (await confirmModal('<?= t('admin.confirm_delete') ?>', { danger: true })) {
        fetch(BASE + '/admin/users/' + id, { method: 'DELETE', headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()} })
            .then(guardAuth).then(r => r.json()).then(data => {
                if (data.success) location.reload();
                else showToast('<?= t('admin.delete_failed') ?>', 'error');
            });
    }
}

function updateUserRole(id, role) {
    fetch(BASE + '/admin/users/' + id, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
        body: JSON.stringify({role: role})
    }).then(guardAuth).then(r => r.json()).then(data => {
        if (data.success) showToast('<?= t('admin.role_updated') ?>', 'success');
        else showToast('<?= t('admin.role_update_failed') ?>', 'error');
    });
}

function updateUserQuota(id, gb) {
    var bytes = parseInt(gb, 10) * 1073741824;
    fetch(BASE + '/admin/users/' + id, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
        body: JSON.stringify({storage_quota: bytes})
    }).then(guardAuth).then(r => r.json()).then(data => {
        if (data.success) showToast('<?= t('admin.quota_updated') ?>', 'success');
        else showToast('<?= t('admin.quota_update_failed') ?>', 'error');
    });
}

function submitCreateUser() {
    var data = {
        username: document.getElementById('new-username').value.trim(),
        email: document.getElementById('new-email').value.trim(),
        password: document.getElementById('new-password').value,
        role: document.getElementById('new-role').value,
        quota: parseInt(document.getElementById('new-quota').value || '10', 10)
    };
    fetch(BASE + '/admin/users', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
        body: JSON.stringify(data)
    }).then(guardAuth).then(r => r.json()).then(data => {
        if (data.success) {
            closeModal('create-user-modal');
            showToast('<?= t('admin.user_created') ?>', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || '<?= t('admin.user_create_failed') ?>', 'error');
        }
    }).catch(() => showToast('<?= t('admin.user_create_failed') ?>', 'error'));
}
</script>
<?php
$extraScripts = ob_get_clean();
include __DIR__ . '/../base.html.php';
?>
