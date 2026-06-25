<?php
/** @var \FileRoll\File\FileEntity $file */
/** @var \FileRoll\Version\Version[] $versions */
$title = t('versions.title') . ' - ' . htmlspecialchars($file->name);
$currentPage = 'files';
$user = $_SESSION['user'] ?? [];
$versions = $versions ?? [];
?>
<?php ob_start(); ?>
<div class="page-header">
    <div class="page-header-left">
        <a href="<?= BASE_URL ?>/?folder=<?= $file->parentId ?>" class="btn btn-ghost btn-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <?= t('actions.back') ?>
        </a>
        <h1 class="page-title"><?= htmlspecialchars($file->name) ?></h1>
        <span class="page-subtitle"><?= t('versions.title') ?></span>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <?php if (empty($versions)): ?>
        <div class="empty-state">
            <p><?= t('versions.no_versions') ?></p>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= t('versions.version') ?></th>
                    <th><?= t('versions.size') ?></th>
                    <th><?= t('versions.created_at') ?></th>
                    <th class="actions-col"><?= t('actions.actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versions as $version): ?>
                <tr>
                    <td><?= t('versions.version_label') ?> <?= $version->versionNumber ?></td>
                    <td><?= $version->getFormattedSize() ?></td>
                    <td><?= htmlspecialchars($version->createdAt ?? '') ?></td>
                    <td class="actions-col">
                        <form method="post" action="<?= BASE_URL ?>/files/<?= $file->id ?>/versions/<?= $version->id ?>/restore" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="btn btn-sm btn-ghost" title="<?= t('files.restore') ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                <?= t('files.restore') ?>
                            </button>
                        </form>
                        <form method="post" action="<?= BASE_URL ?>/files/<?= $file->id ?>/versions/<?= $version->id ?>/delete" class="inline-form confirm-delete-form" data-message="<?= htmlspecialchars(t('files.confirm_delete')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="btn btn-sm btn-ghost btn-danger" title="<?= t('files.delete') ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                <?= t('files.delete') ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
const TRANSLATIONS = <?= json_encode($GLOBALS['__translations'] ?? []) ?>;

document.querySelectorAll('.confirm-delete-form').forEach(function(form) {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (await confirmModal(this.dataset.message, { danger: true })) {
            this.submit();
        }
    });
});
</script>
<?php
$extraScripts = ob_get_clean();
include __DIR__ . '/../base.html.php';
?>