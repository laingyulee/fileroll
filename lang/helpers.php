<?php

declare(strict_types=1);

if (!function_exists('t')) {
    function t(string $key, array $params = []): string {
        return \FileRoll\Core\I18n::getInstance()->t($key, $params);
    }
}
