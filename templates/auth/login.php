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
    <title><?= htmlspecialchars($siteName !== '' ? $siteName . ' - FileRoll' : 'FileRoll') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/public/assets/img/favicon.svg">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/app.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-logo"><?= htmlspecialchars($siteName !== '' ? $siteName : 'FileRoll') ?></h1>
                <p class="auth-subtitle"><?= t('auth.personal_cloud') ?></p>
            </div>
            <?php if ($error !== ''): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/login" class="auth-form">
                <?= $csrf->getTokenField() ?>
                <div class="form-group">
                    <label for="username"><?= t('auth.username') ?></label>
                    <input type="text" id="username" name="username" required autofocus autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password"><?= t('auth.password') ?></label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-block"><?= t('auth.sign_in') ?></button>
            </form>
            <div class="auth-lang-switcher">
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
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var trigger = document.getElementById('lang-trigger');
        var dropdown = document.getElementById('lang-dropdown');
        var panel = document.getElementById('lang-panel');
        var valueInput = document.getElementById('lang-value');
        var form = document.getElementById('lang-form');
        if (!trigger || !dropdown || !panel || !valueInput || !form) return;

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('open');
        });

        panel.querySelectorAll('.lang-option').forEach(function(opt) {
            opt.addEventListener('click', function(e) {
                e.stopPropagation();
                var val = this.dataset.value;
                if (val === valueInput.value) {
                    dropdown.classList.remove('open');
                    return;
                }
                valueInput.value = val;
                form.submit();
            });
        });

        document.addEventListener('click', function() {
            dropdown.classList.remove('open');
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdown.classList.remove('open');
            }
        });
    });
    (function() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('lang_success') === '1') {
            var container = document.createElement('div');
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:12px 20px;box-shadow:0 4px 16px rgba(0,0,0,0.12);font-size:13px;color:#333;font-family:inherit;';
            container.textContent = <?= json_encode(t('settings.language_saved')) ?>;
            document.body.appendChild(container);
            setTimeout(function() { container.remove(); }, 3000);
            params.delete('lang_success');
            var qs = params.toString();
            history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
        }
    })();
    </script>
</body>
</html>
