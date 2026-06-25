<?php
/** @var string $content */
/** @var \FileRoll\User\User|array $user */
/** @var string $title */
$title = $title ?? 'FileRoll';
$currentPage = $currentPage ?? '';
$user = $user ?? null;

$siteName = 'FileRoll';
try {
    $db = \FileRoll\Core\Container::getInstance()->get(\FileRoll\Database\Connection::class);
    $settingsRepo = new \FileRoll\Settings\SettingsRepository($db);
    $siteName = $settingsRepo->get('site_name', 'FileRoll');
} catch (\Throwable $e) { error_log('Failed to load site name: ' . $e->getMessage()); }

function uval($user, string $key, mixed $default = ''): mixed {
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
    return is_array($user) ? ($user[$key] ?? $default) : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName !== '' ? $siteName . ' - FileRoll' : 'FileRoll') ?></title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <script>const BASE = <?= json_encode(BASE_URL) ?>;</script>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/public/assets/img/favicon.svg">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/app.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/vendor/github-dark.min.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="<?= BASE_URL ?>/" class="logo-link">
                    <img src="<?= BASE_URL ?>/public/assets/img/logo.svg" alt="" width="24" height="24" class="logo-icon">
                    <span class="logo"><?= htmlspecialchars($siteName !== '' ? $siteName : 'FileRoll') ?></span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="<?= BASE_URL ?>/" class="nav-item <?= $currentPage === 'files' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                    <span><?= t('nav.files') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/files?starred=1" class="nav-item <?= $currentPage === 'starred' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    <span><?= t('nav.starred') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/files?trash=1" class="nav-item <?= $currentPage === 'trash' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    <span><?= t('nav.trash') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/shared" class="nav-item <?= $currentPage === 'shared' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>
                    <span><?= t('nav.shared') ?></span>
                </a>
                <?php if (uval($user, 'role') === 'admin'): ?>
                <div class="nav-divider"></div>
                <a href="<?= BASE_URL ?>/admin/settings" class="nav-item <?= $currentPage === 'admin' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span><?= t('nav.admin') ?></span>
                </a>
                <?php endif; ?>
            </nav>
            <div class="lang-switcher">
                <form method="post" action="<?= BASE_URL ?>/language" class="lang-form" id="lang-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="language" id="lang-value" value="<?= \FileRoll\Core\I18n::getInstance()->hasUserPreference() ? \FileRoll\Core\I18n::getInstance()->getLocale() : 'auto' ?>">
                    <span class="lang-label"><?= t('settings.language') ?></span>
                    <div class="lang-dropdown" id="lang-dropdown">
                        <button type="button" class="lang-dropdown-trigger" id="lang-trigger">
                            <?php
                            $locales = \FileRoll\Core\I18n::getInstance()->getAvailableLocales();
                            $curLocale = \FileRoll\Core\I18n::getInstance()->getLocale();
                            $hasPref = \FileRoll\Core\I18n::getInstance()->hasUserPreference();
                            echo $hasPref ? ($locales[$curLocale] ?? $curLocale) : t('settings.language_auto');
                            ?>
                            <svg width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="lang-dropdown-panel" id="lang-panel">
                            <button type="button" class="lang-option <?= !$hasPref ? 'active' : '' ?>" data-value="auto"><?= t('settings.language_auto') ?></button>
                            <div class="lang-option-divider"></div>
                            <?php foreach ($locales as $code => $name): ?>
                            <button type="button" class="lang-option <?= $code === $curLocale && $hasPref ? 'active' : '' ?>" data-value="<?= $code ?>"><?= $name ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="sidebar-footer">
                <div class="storage-info">
                    <?php
                    $storageUsed = $storageUsed ?? 0;
                    $storageQuota = $storageQuota ?? 0;
                    $isUnlimited = $storageQuota <= 0;
                    ?>
                    <div class="storage-header">
                        <span class="storage-label"><?= t('nav.storage') ?></span>
                        <span class="storage-text"><?= formatSize($storageUsed) ?> / <?= $isUnlimited ? t('nav.unlimited') : formatSize($storageQuota) ?></span>
                    </div>
                    <?php if (!$isUnlimited): ?>
                    <div class="storage-bar">
                        <div class="storage-fill" style="width: <?= min(100, $storageUsed / $storageQuota * 100) ?>%"></div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="user-panel">
                    <div class="user-avatar">
                        <span class="avatar-letter"><?= strtoupper(substr(uval($user, 'display_name', uval($user, 'username', 'U')), 0, 1)) ?></span>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars(uval($user, 'display_name', uval($user, 'username', 'User'))) ?></span>
                        <?php if (uval($user, 'role') === 'admin'): ?>
                        <span class="user-role"><?= t('nav.administrator') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-actions">
                        <a href="<?= BASE_URL ?>/settings" class="user-action-btn" title="<?= t('nav.settings') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        </a>
                        <form method="post" action="<?= BASE_URL ?>/logout" class="logout-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="user-action-btn" title="<?= t('nav.sign_out') ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>
        <main class="main-content" id="main-content">
            <?= $content ?>
            <div id="modal-overlay" class="modal-overlay hidden"></div>

            <div id="dialog-modal" class="modal hidden">
                <div class="modal-dialog dialog-dialog">
                    <div class="modal-header">
                        <h3 id="dialog-title"></h3>
                        <button class="modal-close" onclick="cancelDialog()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p id="dialog-message" class="dialog-message"></p>
                        <div id="dialog-prompt-group" class="form-group hidden">
                            <input type="text" id="dialog-prompt-input" class="dialog-prompt-input">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="dialog-secondary-btn" class="btn btn-secondary" onclick="cancelDialog()"></button>
                        <button id="dialog-primary-btn" class="btn btn-primary"></button>
                    </div>
                </div>
            </div>

            <footer class="site-footer">
                <?php if ($siteName !== '' && $siteName !== 'FileRoll'): ?>
                <span class="footer-site-name"><?= htmlspecialchars($siteName) ?></span>
                <span class="footer-sep">|</span>
                <?php endif; ?>
                Powered by <strong>FileRoll</strong> v1.0.0
            </footer>
        </main>
    </div>
    <div id="toast-container" class="toast-container"></div>
    <script src="<?= BASE_URL ?>/public/assets/js/app.js?v=4"></script>
    <script src="<?= BASE_URL ?>/public/assets/vendor/marked.min.js"></script>
    <script>
    window.I18N_MESSAGES = {
        session_expired: '<?= addslashes(t('error.session_expired')) ?>',
        ok: '<?= addslashes(t('dialog.ok')) ?>'
    };
    window.PREVIEW_UNSAVED_TEXT = '<?= addslashes(t('preview.unsaved')) ?>';
    </script>
    <script src="<?= BASE_URL ?>/public/assets/js/preview.js?v=11"></script>
    <?= $extraScripts ?? '' ?>
    <script>
    (function() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('lang_success') === '1') {
            showToast('<?= addslashes(t('settings.language_saved')) ?>');
            params.delete('lang_success');
            var qs = params.toString();
            var url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
            history.replaceState(null, '', url);
        }
    })();
    </script>
    <script>
    (function() {
        var currentResolve = null;
        var dialogReturnsValue = false;

        function setupDialog(options) {
            var modal = document.getElementById('dialog-modal');
            var title = document.getElementById('dialog-title');
            var message = document.getElementById('dialog-message');
            var promptGroup = document.getElementById('dialog-prompt-group');
            var promptInput = document.getElementById('dialog-prompt-input');
            var primaryBtn = document.getElementById('dialog-primary-btn');
            var secondaryBtn = document.getElementById('dialog-secondary-btn');

            title.textContent = options.title || '';
            message.textContent = options.message || '';
            message.style.display = options.message ? '' : 'none';

            dialogReturnsValue = !!options.showPrompt;
            if (dialogReturnsValue) {
                promptGroup.classList.remove('hidden');
                promptInput.value = options.defaultValue || '';
            } else {
                promptGroup.classList.add('hidden');
                promptInput.value = '';
            }

            primaryBtn.textContent = options.primaryText || '<?= t('dialog.ok') ?>';
            primaryBtn.className = 'btn ' + (options.primaryClass || 'btn-primary');
            primaryBtn.onclick = function() {
                closeModal('dialog-modal');
                if (currentResolve) {
                    currentResolve(dialogReturnsValue ? promptInput.value : true);
                    currentResolve = null;
                }
            };

            if (options.secondaryText) {
                secondaryBtn.classList.remove('hidden');
                secondaryBtn.textContent = options.secondaryText;
            } else {
                secondaryBtn.classList.add('hidden');
            }

            modal.classList.remove('hidden');
        }

        window.cancelDialog = function() {
            closeModal('dialog-modal');
            if (currentResolve) {
                currentResolve(dialogReturnsValue ? null : false);
                currentResolve = null;
            }
        };

        window.confirmModal = function(message, options) {
            options = options || {};
            return new Promise(function(resolve) {
                currentResolve = resolve;
                setupDialog({
                    title: options.title || '<?= t('dialog.confirm') ?>',
                    message: message,
                    primaryText: options.confirmText || '<?= t('dialog.confirm') ?>',
                    primaryClass: options.danger ? 'btn-danger' : 'btn-primary',
                    secondaryText: options.cancelText || '<?= t('dialog.cancel') ?>',
                    showPrompt: false
                });
                openModal('dialog-modal');
                document.getElementById('dialog-primary-btn').focus();
            });
        };

        window.alertModal = function(message, options) {
            options = options || {};
            return new Promise(function(resolve) {
                currentResolve = resolve;
                setupDialog({
                    title: options.title || '<?= t('dialog.ok') ?>',
                    message: message,
                    primaryText: options.okText || '<?= t('dialog.ok') ?>',
                    secondaryText: null,
                    showPrompt: false
                });
                openModal('dialog-modal');
                document.getElementById('dialog-primary-btn').focus();
            });
        };

        window.promptModal = function(message, defaultValue) {
            return new Promise(function(resolve) {
                currentResolve = resolve;
                setupDialog({
                    title: message,
                    message: '',
                    primaryText: '<?= t('dialog.ok') ?>',
                    secondaryText: '<?= t('dialog.cancel') ?>',
                    showPrompt: true,
                    defaultValue: defaultValue || ''
                });
                openModal('dialog-modal');
                setTimeout(function() {
                    var input = document.getElementById('dialog-prompt-input');
                    if (input) input.focus();
                }, 50);
            });
        };
    })();
    </script>
</body>
</html>
<?php
function formatSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>