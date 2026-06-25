<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - FileRoll</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/public/assets/img/favicon.svg">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/app.css">
</head>
<body class="error-page">
    <div class="error-container">
        <h1 class="error-code">404</h1>
        <h2 class="error-title"><?php try { echo t('error.page_not_found'); } catch (\Throwable $e) { echo 'Page Not Found'; } ?></h2>
        <p class="error-message"><?= htmlspecialchars($error ?? (function_exists('t') ? t('error.page_not_found_msg') : 'The page you are looking for does not exist.')) ?></p>
        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/" class="btn btn-primary"><?php try { echo t('error.go_home'); } catch (\Throwable $e) { echo 'Go Home'; } ?></a>
    </div>
</body>
</html>
