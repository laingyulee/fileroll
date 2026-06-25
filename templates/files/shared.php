<?php
/** @var array $sharesWithFiles */
$sharesWithFiles = $sharesWithFiles ?? [];
$success = $success ?? '';
?>
<?php ob_start(); ?>
<div class="page-header">
    <div class="breadcrumb">
        <a href="<?= BASE_URL ?>/" class="breadcrumb-item"><?= t('files.home') ?></a>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-item" style="color:var(--text-primary);font-weight:500"><?= t('nav.shared') ?></span>
    </div>
</div>

<?php if ($success !== ''): ?>
<div class="alert alert-success">
    <?php if ($success === 'revoked'): ?><?= t('shared.link_revoked') ?>
    <?php elseif ($success === 'updated'): ?><?= t('shared.link_updated') ?>
    <?php else: ?><?= t('shared.action_done') ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($sharesWithFiles)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-tertiary);margin-bottom:12px;"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>
    <p><?= t('shared.no_links') ?></p>
</div>
<?php else: ?>
<div class="share-list">
    <?php foreach ($sharesWithFiles as $item):
        $share = $item['share'];
        $file = $item['file'];
        $status = $share->isValid() ? ($share->isActive ? 'active' : 'revoked') : 'expired';
        $shareUrl = BASE_URL . $share->getShareUrl();
    ?>
    <div class="share-card" data-share-id="<?= $share->id ?>">
        <div class="share-card-header">
            <div class="share-card-info">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-tertiary);flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                <span class="share-card-filename"><?= htmlspecialchars($file?->name ?? t('shared.deleted_file')) ?></span>
                <span class="share-status share-status-<?= $status ?>"><?= ucfirst($status) ?></span>
            </div>
            <div class="share-card-actions">
                <button class="btn btn-sm" onclick="copyShareLink('<?= htmlspecialchars($shareUrl) ?>')" title="<?= t('shared.copy_link') ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                </button>
                <button class="btn btn-sm" onclick="openEditShare(<?= $share->id ?>)" title="<?= t('shared.edit') ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                </button>
                <?php if ($share->isActive): ?>
                <button class="btn btn-sm btn-danger" onclick="revokeShare(<?= $share->id ?>)" title="<?= t('shared.revoke') ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="share-card-meta">
            <span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <?= $share->permissionLevel === 'write' ? t('share.read_write') : t('share.read_only') ?>
            </span>
            <span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <?= $share->expiresAt ? t('shared.expires_prefix') . ' ' . date('M j, Y', strtotime($share->expiresAt)) : t('shared.never_expires') ?>
            </span>
            <span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                <?= $share->downloadCount ?><?= $share->maxDownloads !== null ? ' / ' . $share->maxDownloads : '' ?> <?= t('shared.downloads_unit') ?>
            </span>
            <?php if ($share->hasPassword()): ?>
            <span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <?= t('shared.password_protected') ?>
            </span>
            <?php endif; ?>
            <span class="share-meta-created">
                <?= t('shared.created') ?> <?= $share->createdAt ? date('M j, Y', strtotime($share->createdAt)) : '-' ?>
            </span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div id="edit-share-modal" class="modal hidden">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><?= t('shared.edit_title') ?></h3>
            <button class="modal-close" onclick="closeModal('edit-share-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="edit-permission"><?= t('share.permission') ?></label>
                <select id="edit-permission">
                    <option value="read"><?= t('share.read_only') ?></option>
                    <option value="write"><?= t('share.read_write') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit-expires"><?= t('share.expires_in') ?></label>
                <select id="edit-expires">
                    <option value="0"><?= t('share.never') ?></option>
                    <option value="1"><?= t('share.one_hour') ?></option>
                    <option value="24"><?= t('share.twenty_four_hours') ?></option>
                    <option value="168"><?= t('share.one_week') ?></option>
                    <option value="720"><?= t('share.thirty_days') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit-max-downloads"><?= t('shared.max_downloads_hint') ?></label>
                <input type="number" id="edit-max-downloads" min="0" value="0">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('edit-share-modal')"><?= t('files.cancel') ?></button>
                <button class="btn btn-primary" onclick="saveShareEdit()"><?= t('shared.save') ?></button>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
let editingShareId = null;

function copyShareLink(url) {
    var fullUrl = url.indexOf('//') === -1 ? window.location.origin + url : url;
    navigator.clipboard.writeText(fullUrl).then(() => showToast('<?= t('shared.link_copied') ?>', 'success'));
}

async function revokeShare(id) {
    if (!await confirmModal('<?= t('shared.confirm_revoke') ?>', { danger: true })) return;
    fetch(BASE + '/shares/' + id, {
        method: 'DELETE',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()}
    }).then(guardAuth).then(r => r.json()).then(data => {
        if (data.success) location.reload();
        else showToast('<?= t('shared.revoke_failed') ?>', 'error');
    });
}

function openEditShare(id) {
    editingShareId = id;
    var card = document.querySelector('.share-card[data-share-id="' + id + '"]');
    if (!card) return;
    openModal('edit-share-modal');
}

function saveShareEdit() {
    if (!editingShareId) return;
    fetch(BASE + '/shares/' + editingShareId, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
        body: JSON.stringify({
            permission_level: document.getElementById('edit-permission').value,
            expires_in: parseInt(document.getElementById('edit-expires').value, 10),
            max_downloads: parseInt(document.getElementById('edit-max-downloads').value, 10)
        })
    }).then(guardAuth).then(r => r.json()).then(data => {
        if (data.success) {
            closeModal('edit-share-modal');
            showToast('<?= t('shared.update_success') ?>', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('<?= t('shared.update_failed') ?>', 'error');
        }
    });
}
</script>
<?php
$extraScripts = ob_get_clean();
include __DIR__ . '/../base.html.php';
?>
