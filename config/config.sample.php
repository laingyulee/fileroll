<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'FileRoll',
        'url' => '/',
        'debug' => false,
        'timezone' => 'UTC',
    ],

    'database' => [
        'driver' => 'sqlite',
        'sqlite' => [
            'path' => __DIR__ . '/../storage/fileroll.db',
        ],
        'mysql' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'fileroll',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
    ],

    'storage' => [
        'content_path' => __DIR__ . '/../storage/content',
        'temp_path' => __DIR__ . '/../storage/temp',
        'trash_path' => __DIR__ . '/../storage/trash',
        'default_quota' => 107374182400,
    ],

    'session' => [
        'lifetime' => 7200,
        'name' => 'fileroll_session',
        'cookie_params' => [
            'httponly' => true,
            'secure' => true,
            'samesite' => 'Strict',
        ],
    ],

    'security' => [
        'csrf_enabled' => true,
        'rate_limit_login' => 5,
        'rate_limit_window' => 900,
        'max_upload_size' => 5368709120,
    ],

    'files' => [
        'batch_download_max_size' => 100 * 1024 * 1024, // 100MB
    ],
];
