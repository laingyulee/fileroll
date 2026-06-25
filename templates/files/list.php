<?php
/** @var \FileRoll\File\FileEntity[] $files */
/** @var array $breadcrumb */
/** @var int|null $parentId */
/** @var int $storageUsed */
/** @var int $storageQuota */
/** @var array $user */
$files = $files ?? [];
$breadcrumb = $breadcrumb ?? [];
$parentId = $parentId ?? null;
$storageUsed = $storageUsed ?? 0;
$storageQuota = $storageQuota ?? 0;
$user = $user ?? ['username' => 'User', 'role' => 'user'];
$base = BASE_URL;
$isStarred = $isStarred ?? false;
$isTrash = $isTrash ?? false;
$trashAutoClean = $trashAutoClean ?? '0';
$trashGraceDays = $trashGraceDays ?? 30;
$pageTitle = $isStarred ? t('nav.starred') : ($isTrash ? t('nav.trash') : t('nav.files'));
?>
<?php ob_start(); ?>
<div class="page-header">
    <div class="breadcrumb">
        <?php if ($isStarred || $isTrash): ?>
        <a href="<?= $base ?>/" class="breadcrumb-item"><?= t('files.home') ?></a>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-item" style="color:var(--text-primary);font-weight:500"><?= $pageTitle ?></span>
        <?php else: ?>
        <a href="<?= $base ?>/" class="breadcrumb-item"><?= t('files.home') ?></a>
        <?php foreach ($breadcrumb as $crumb): ?>
            <span class="breadcrumb-sep">/</span>
            <a href="<?= $base ?>/files?folder=<?= $crumb->id ?>" class="breadcrumb-item"><?= htmlspecialchars($crumb->name) ?></a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="page-actions">
        <?php if ($isTrash && !empty($files)): ?>
        <button class="btn btn-danger" onclick="emptyTrash()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            <?= t('files.empty_trash') ?>
        </button>
        <?php endif; ?>
        <?php if (!$isStarred && !$isTrash): ?>
        <button class="btn btn-primary" id="upload-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            <?= t('files.upload') ?>
        </button>
        <button class="btn btn-secondary" id="new-folder-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>
            <?= t('files.new_folder') ?>
        </button>
        <button class="btn btn-secondary" id="new-file-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
            <?= t('files.new_file') ?>
        </button>
        <?php endif; ?>
    </div>
</div>

        <div id="upload-zone" class="upload-zone hidden" data-folder-id="<?= $parentId ?? '' ?>">
    <div class="upload-zone-content">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
        <p><?= t('files.drag_drop') ?></p>
        <input type="file" id="file-input" multiple style="display:none">
    </div>
    <div id="upload-progress" class="upload-progress hidden"></div>
</div>

<div id="batch-toolbar" class="batch-toolbar hidden">
    <span id="batch-count" class="batch-count"></span>
    <div class="batch-actions" id="batch-normal-actions">
        <button class="btn btn-secondary" onclick="batchMove()"><?= t('files.move') ?></button>
        <button class="btn btn-secondary" onclick="batchCopy()"><?= t('files.copy') ?></button>
        <button class="btn btn-secondary" onclick="batchDownload()"><?= t('files.download') ?></button>
        <button class="btn btn-secondary" onclick="clearSelection()"><?= t('files.batch_cancel') ?></button>
        <button class="btn btn-danger" onclick="batchTrash()"><?= t('files.batch_trash') ?></button>
    </div>
    <div class="batch-actions hidden" id="batch-clipboard-actions">
        <button class="btn btn-secondary" onclick="cancelClipboard()"><?= t('files.batch_cancel') ?></button>
        <button class="btn btn-primary" onclick="batchPaste(<?= json_encode($parentId) ?>)" id="paste-btn"><?= t('files.paste_here') ?></button>
    </div>
</div>

<div class="file-browser">
    <?php if (empty($files)): ?>
    <div class="empty-state">
        <?php if ($isStarred): ?>
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        <h3><?= t('files.no_starred') ?></h3>
        <p><?= t('files.starred_hint') ?></p>
        <?php elseif ($isTrash): ?>
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        <h3><?= t('files.trash_empty') ?></h3>
        <p><?= t('files.trash_hint') ?></p>
        <?php else: ?>
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
        <h3><?= t('files.folder_empty') ?></h3>
        <p><?= t('files.upload_hint') ?></p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="file-table">
        <thead>
            <tr>
                <th class="file-select-col">
                    <input type="checkbox" id="select-all">
                </th>
                <th class="file-name-col"><?= t('files.col_name') ?></th>
                <th class="file-size-col"><?= t('files.col_size') ?></th>
                <th class="file-modified-col"><?= t('files.col_modified') ?></th>
                <?php if ($isTrash && $trashAutoClean === '1'): ?>
                <th class="file-grace-col"><?= t('files.expires') ?></th>
                <?php endif; ?>
                <th class="file-actions-col"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file): ?>
            <tr class="file-row <?= $file->isFolder ? 'folder-row' : 'file-row-item' ?>"
                data-id="<?= $file->id ?>"
                data-type="<?= $file->isFolder ? 'folder' : 'file' ?>"
                data-name="<?= htmlspecialchars($file->name) ?>"
                data-size="<?= $file->size ?? 0 ?>"
                data-mime="<?= htmlspecialchars($file->mimeType ?? '') ?>"
                data-folder="<?= $parentId ?>">
                <td class="file-select-col">
                    <input type="checkbox" class="file-select" data-id="<?= $file->id ?>" data-type="<?= $file->isFolder ? 'folder' : 'file' ?>">
                </td>
                <td class="file-name">
                    <?php if ($file->isFolder): ?>
                    <a href="<?= $base ?>/files?folder=<?= $file->id ?>" class="file-link">
                        <svg class="file-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        <?= htmlspecialchars($file->name) ?>
                    </a>
                    <?php else: ?>
                    <span class="file-link" onclick="Preview.open(<?= $file->id ?>, <?= htmlspecialchars(json_encode($file->name)) ?>, <?= $file->size ?? 0 ?>, <?= htmlspecialchars(json_encode($file->mimeType ?? '')) ?>, <?= $parentId ?? 'null' ?>)">
                        <svg class="file-icon icon-<?= $file->getIcon() ?>" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        <?= htmlspecialchars($file->name) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td class="file-size"><?= $file->isFolder ? '-' : $file->getFormattedSize() ?></td>
                <td class="file-modified"><?= $file->updatedAt ? date('M j, Y', strtotime($file->updatedAt)) : '-' ?></td>
                <?php if ($isTrash && $trashAutoClean === '1'): ?>
                <td class="file-grace">
                    <?php
                    if ($file->trashedAt) {
                        $expires = strtotime($file->trashedAt . ' +' . $trashGraceDays . ' days');
                        $remaining = $expires - time();
                        if ($remaining <= 0) {
                            echo '<span class="grace-expired">' . t('files.expiring_soon') . '</span>';
                        } elseif ($remaining < 86400) {
                            echo '<span class="grace-hours">' . t('files.expires_hours') . '</span>';
                        } else {
                            $days = ceil($remaining / 86400);
                            echo t('files.expires_days', ['days' => $days]);
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <?php endif; ?>
                <td class="file-actions">
                    <?php if ($isTrash): ?>
                    <button class="btn-icon" onclick="restoreFile(<?= $file->id ?>)" title="<?= t('files.restore') ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                    </button>
                    <button class="btn-icon btn-icon-danger" onclick="deleteFile(<?= $file->id ?>)" title="<?= t('files.delete_permanently') ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                    <?php else: ?>
                    <button class="btn-icon" onclick="toggleStar(<?= $file->id ?>)" title="<?= t('files.star') ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= $file->isStarred ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    </button>
                    <?php if (!$file->isFolder): ?>
                    <button class="btn-icon" onclick="Preview.open(<?= $file->id ?>, <?= htmlspecialchars(json_encode($file->name)) ?>, <?= $file->size ?? 0 ?>, <?= htmlspecialchars(json_encode($file->mimeType ?? '')) ?>, <?= $parentId ?? 'null' ?>)" title="<?= t('files.preview') ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                    <a href="<?= $base ?>/files/<?= $file->id ?>" class="btn-icon auth-check-download" title="<?= t('files.download') ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    </a>
                    <button class="btn-icon" onclick="showShareDialog(<?= $file->id ?>)" title="<?= t('files.share') ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                    </button>
                    <?php endif; ?>
                    <button class="btn-icon" onclick="renameFile(<?= $file->id ?>, '<?= htmlspecialchars($file->name) ?>')" title="<?= t('files.rename') ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    <button class="btn-icon btn-icon-danger" onclick="trashFile(<?= $file->id ?>)" title="<?= t('files.delete') ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div id="new-folder-modal" class="modal hidden">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><?= t('files.new_folder') ?></h3>
            <button class="modal-close" onclick="closeModal('new-folder-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="folder-name"><?= t('files.folder_name') ?></label>
                <input type="text" id="folder-name" placeholder="<?= t('files.new_folder') ?>">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('new-folder-modal')"><?= t('files.cancel') ?></button>
            <button class="btn btn-primary" onclick="createFolder()"><?= t('files.create') ?></button>
        </div>
    </div>
</div>

<div id="new-file-modal" class="modal hidden">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><?= t('files.new_file') ?></h3>
            <button class="modal-close" onclick="closeModal('new-file-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="file-name"><?= t('files.file_name') ?></label>
                <input type="text" id="file-name" placeholder="example.txt">
                <span class="form-hint"><?= t('files.file_hint') ?></span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('new-file-modal')"><?= t('files.cancel') ?></button>
            <button class="btn btn-primary" onclick="createFile()"><?= t('files.create') ?></button>
        </div>
    </div>
</div>

<div id="rename-modal" class="modal hidden">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><?= t('files.rename') ?></h3>
            <button class="modal-close" onclick="closeModal('rename-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="rename-input"><?= t('files.new_name') ?></label>
                <input type="text" id="rename-input">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('rename-modal')"><?= t('files.cancel') ?></button>
            <button class="btn btn-primary" onclick="confirmRename()"><?= t('files.rename') ?></button>
        </div>
    </div>
</div>

<div id="share-modal" class="modal hidden">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><?= t('share.title') ?></h3>
            <button class="modal-close" onclick="closeModal('share-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="share-file-id">
            <div class="form-group">
                <label for="share-permission"><?= t('share.permission') ?></label>
                <select id="share-permission">
                    <option value="read"><?= t('share.read_only') ?></option>
                    <option value="write"><?= t('share.read_write') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="share-password"><?= t('share.password_optional') ?></label>
                <input type="text" id="share-password" placeholder="<?= t('share.password_placeholder') ?>">
            </div>
            <div class="form-group">
                <label for="share-expires"><?= t('share.expires_in') ?></label>
                <select id="share-expires">
                    <option value=""><?= t('share.never') ?></option>
                    <option value="1"><?= t('share.one_hour') ?></option>
                    <option value="24"><?= t('share.hours_24') ?></option>
                    <option value="168"><?= t('share.one_week') ?></option>
                    <option value="720"><?= t('share.days_30') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="share-max-downloads"><?= t('share.max_downloads') ?></label>
                <input type="number" id="share-max-downloads" placeholder="Unlimited" min="1">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('share-modal')"><?= t('files.cancel') ?></button>
            <button class="btn btn-primary" onclick="createShare()"><?= t('share.create_link') ?></button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
const TRANSLATIONS = {
    upload_success: <?= json_encode(t('files.uploaded')) ?>,
    upload_success_plural: <?= json_encode(t('files.uploaded_plural')) ?>
};
</script>
<script src="<?= BASE_URL ?>/public/assets/js/upload.js?v=2"></script>
<script>
const currentFolder = <?= json_encode($parentId) ?>;
function toggleStar(id) {
    fetch(BASE + '/files/' + id + '/star', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()} })
        .then(guardAuth).then(r => r.json()).then(() => { showToast('<?= t('files.star_updated') ?>', 'success'); setTimeout(location.reload.bind(location), 1500); });
}
function showShareDialog(fileId) {
    document.getElementById('share-file-id').value = fileId;
    openModal('share-modal');
}
function renameFile(id, currentName) {
    document.getElementById('rename-input').value = currentName;
    document.getElementById('rename-modal').dataset.fileId = id;
    openModal('rename-modal');
}
function confirmRename() {
    const id = document.getElementById('rename-modal').dataset.fileId;
    const name = document.getElementById('rename-input').value;
    fetch(BASE + '/files/' + id + '/rename', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
        body: JSON.stringify({name: name})
    }).then(guardAuth).then(r => r.json()).then(() => { closeModal('rename-modal'); showToast('<?= t('files.renamed') ?>', 'success'); setTimeout(location.reload.bind(location), 1500); });
}
async function trashFile(id) {
    if (await confirmModal('<?= t('files.confirm_trash') ?>')) {
        fetch(BASE + '/files/' + id + '/trash', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()} })
            .then(guardAuth).then(r => r.json()).then(() => { showToast('<?= t('files.trashed') ?>', 'success'); setTimeout(location.reload.bind(location), 1500); });
    }
}
function restoreFile(id) {
    fetch(BASE + '/files/' + id + '/restore', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()} })
        .then(guardAuth).then(r => r.json()).then(() => { showToast('<?= t('files.restored') ?>', 'success'); setTimeout(location.reload.bind(location), 1500); });
}
async function deleteFile(id) {
    if (await confirmModal('<?= t('files.confirm_delete') ?>', { danger: true })) {
        fetch(BASE + '/files/' + id, { method: 'DELETE', headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()} })
            .then(guardAuth).then(r => r.json()).then(data => {
                if (data.missing) {
                    showToast('<?= t('files.missing_cleaned') ?>', 'info');
                } else {
                    showToast('<?= t('files.deleted') ?>', 'success');
                }
                setTimeout(location.reload.bind(location), 1500);
            });
    }
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.file-select:checked')).map(cb => parseInt(cb.dataset.id));
}

function updateBatchToolbar() {
    const ids = getSelectedIds();
    const toolbar = document.getElementById('batch-toolbar');
    const countEl = document.getElementById('batch-count');
    const selectAll = document.getElementById('select-all');
    const allRows = document.querySelectorAll('.file-select');
    const clipboard = getClipboard();

    if (clipboard) {
        toolbar.classList.remove('hidden');
        updateBatchToolbarForClipboard(clipboard.ids.length);
    } else if (ids.length > 0) {
        toolbar.classList.remove('hidden');
        restoreBatchToolbarNormal();
        countEl.textContent = '<?= t('files.selected_count', ['count' => '__COUNT__']) ?>'.replace('__COUNT__', ids.length);
    } else {
        toolbar.classList.add('hidden');
        restoreBatchToolbarNormal();
    }

    selectAll.checked = ids.length > 0 && ids.length === allRows.length;
}

function clearSelection() {
    document.querySelectorAll('.file-select').forEach(cb => cb.checked = false);
    document.getElementById('select-all').checked = false;
    updateBatchToolbar();
}

async function batchTrash() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;

    const message = '<?= t('files.batch_trash_confirm', ['count' => '__COUNT__']) ?>'.replace('__COUNT__', ids.length);
    if (!await confirmModal(message, { danger: true })) return;

    const errors = [];
    for (const id of ids) {
        try {
            const res = await fetch(BASE + '/files/' + id + '/trash', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken()
                }
            });
            await guardAuth(res);
            const data = await res.json();
            if (!data.success) {
                errors.push(id);
            }
        } catch (e) {
            errors.push(id);
        }
    }

    if (errors.length === 0) {
        showToast('<?= t('files.batch_trashed') ?>', 'success');
    } else {
        showToast('<?= t('files.batch_trash_partial') ?> (' + errors.length + '/' + ids.length + ')', 'error');
    }
    setTimeout(() => location.reload(), 1500);
}

function setClipboard(mode, ids) {
    sessionStorage.setItem('batch_clipboard', JSON.stringify({ mode, ids, timestamp: Date.now() }));
}

function getClipboard() {
    const raw = sessionStorage.getItem('batch_clipboard');
    if (!raw) return null;
    const data = JSON.parse(raw);
    if (Date.now() - data.timestamp > 30 * 60 * 1000) {
        sessionStorage.removeItem('batch_clipboard');
        return null;
    }
    return data;
}

function clearClipboard() {
    sessionStorage.removeItem('batch_clipboard');
}

function batchMove() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    setClipboard('move', ids);
    updateBatchToolbarForClipboard(ids.length);
}

function batchCopy() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    setClipboard('copy', ids);
    updateBatchToolbarForClipboard(ids.length);
}

function cancelClipboard() {
    clearClipboard();
    clearSelection();
}

async function batchPaste(targetFolderId) {
    const clipboard = getClipboard();
    if (!clipboard) return;

    const errors = [];
    for (const id of clipboard.ids) {
        try {
            const endpoint = clipboard.mode === 'move' ? '/move' : '/copy';
            const res = await fetch(BASE + '/files/' + id + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken()
                },
                body: JSON.stringify({ target_folder_id: targetFolderId })
            });
            await guardAuth(res);
            const data = await res.json();
            if (!data.success) {
                errors.push(id);
            }
        } catch (e) {
            errors.push(id);
        }
    }

    clearClipboard();
    if (errors.length === 0) {
        const message = clipboard.mode === 'move' ? '<?= t('files.batch_moved') ?>' : '<?= t('files.batch_copied') ?>';
        showToast(message, 'success');
    } else {
        const message = clipboard.mode === 'move' ? '<?= t('files.batch_move_partial') ?>' : '<?= t('files.batch_copy_partial') ?>';
        showToast(message + ' (' + errors.length + '/' + clipboard.ids.length + ')', 'error');
    }
    setTimeout(() => location.reload(), 1500);
}

async function batchDownload() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;

    try {
        const res = await fetch(BASE + '/files/batch-download', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken()
            },
            body: JSON.stringify({ ids })
        });
        await guardAuth(res);
        if (!res.ok) {
            const data = await res.json();
            showToast(data.error || '<?= t('files.batch_download_too_large') ?>', 'error');
            return;
        }
        const data = await res.json();
        window.location.href = BASE + data.download_url;
    } catch (e) {
        showToast('<?= t('files.error') ?>', 'error');
    }
}

function updateBatchToolbarForClipboard(count) {
    document.getElementById('batch-normal-actions').classList.add('hidden');
    document.getElementById('batch-clipboard-actions').classList.remove('hidden');
    document.getElementById('batch-count').textContent = '<?= t('files.clipboard_hint', ['count' => '__COUNT__']) ?>'.replace('__COUNT__', count);
}

function restoreBatchToolbarNormal() {
    document.getElementById('batch-normal-actions').classList.remove('hidden');
    document.getElementById('batch-clipboard-actions').classList.add('hidden');
}

document.getElementById('select-all')?.addEventListener('change', function () {
    document.querySelectorAll('.file-select').forEach(cb => cb.checked = this.checked);
    updateBatchToolbar();
});

document.querySelectorAll('.file-select').forEach(cb => {
    cb.addEventListener('change', updateBatchToolbar);
});

async function emptyTrash() {
    if (await confirmModal('<?= t('files.confirm_empty_trash') ?>', { danger: true })) {
        fetch(BASE + '/files/trash', { method: 'DELETE', headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()} })
            .then(guardAuth).then(r => r.json()).then(data => {
                if (data.success) {
                    showToast('<?= t('files.trash_emptied') ?>', 'success');
                    setTimeout(location.reload.bind(location), 1500);
                }
            });
    }
}
function createFolder() {
    const name = document.getElementById('folder-name').value;
    if (!name) return;
    fetch(BASE + '/folders', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
        body: JSON.stringify({name: name, parent_id: currentFolder})
    }).then(guardAuth).then(r => r.json()).then(() => { closeModal('new-folder-modal'); showToast('<?= t('files.folder_created') ?>', 'success'); setTimeout(location.reload.bind(location), 1500); });
}
async function createShare() {
    const fileId = document.getElementById('share-file-id').value;
    fetch(BASE + '/shares', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
        body: JSON.stringify({
            file_id: fileId,
            permission_level: document.getElementById('share-permission').value,
            password: document.getElementById('share-password').value,
            expires_in: document.getElementById('share-expires').value,
            max_downloads: document.getElementById('share-max-downloads').value
        })
    }).then(guardAuth).then(r => r.json()).then(async data => {
        if (data.url) {
            const url = window.location.origin + BASE + data.url;
            await promptModal('<?= t('files.share_url') ?>', url);
            showToast('<?= t('files.share_created') ?>', 'success');
        }
        closeModal('share-modal');
    });
}
document.getElementById('new-folder-btn')?.addEventListener('click', () => openModal('new-folder-modal'));
document.getElementById('new-file-btn')?.addEventListener('click', () => {
    document.getElementById('file-name').value = '';
    openModal('new-file-modal');
    setTimeout(() => document.getElementById('file-name').focus(), 100);
});
function createFile() {
    const name = document.getElementById('file-name').value.trim();
    if (!name) return;
    if (!name.includes('.')) { showToast('<?= t('files.ext_required') ?>', 'warning'); return; }
    fetch(BASE + '/files/create', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
        body: JSON.stringify({name: name, parent_id: currentFolder})
    }).then(guardAuth).then(r => r.json()).then(data => {
        closeModal('new-file-modal');
        if (data.success && data.file) {
            Preview.open(data.file.id, data.file.name, parseInt(data.file.size) || 0, data.file.mime_type, currentFolder, true);
        } else {
            showToast(data.error || '<?= t('files.create_failed') ?>', 'error');
        }
    }).catch(() => { showToast('<?= t('files.create_failed') ?>', 'error'); });
}

updateBatchToolbar();
</script>
<?php
$extraScripts = ob_get_clean();
include __DIR__ . '/../base.html.php';
?>
