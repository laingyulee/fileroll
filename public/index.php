<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \FileRoll\Core\App();
$app->bootstrap();
$app->run();
