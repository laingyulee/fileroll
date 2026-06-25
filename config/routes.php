<?php

declare(strict_types=1);

use FileRoll\User\UserController;
use FileRoll\File\FileController;
use FileRoll\File\FolderController;
use FileRoll\Version\VersionController;
use FileRoll\Share\ShareController;
use FileRoll\Admin\AdminController;
use FileRoll\Settings\SettingsController;

return [
    // OwnCloud/Nextcloud client compatibility
    'GET /status.php' => [UserController::class, 'status'],
    'GET /ocs/v2.php/cloud/capabilities' => [UserController::class, 'capabilities'],
    'GET /ocs/v2.php/cloud/user' => [UserController::class, 'cloudUser'],
    'GET /graph/v1.0/me/drives' => [UserController::class, 'graphDrives'],

    // Auth
    'GET /login' => [UserController::class, 'login'],
    'POST /login' => [UserController::class, 'authenticate'],
    'POST /logout' => [UserController::class, 'logout'],
    'POST /language' => [UserController::class, 'switchLanguage'],

    // Protected routes
    'GET /' => [FileController::class, 'index'],
    'GET /files' => [FileController::class, 'listItems'],
    'POST /files/upload' => [FileController::class, 'upload'],
    'POST /files/create' => [FileController::class, 'create'],
    'GET /files/{id}' => [FileController::class, 'download'],
    'GET /files/{id}/preview' => [FileController::class, 'preview'],
    'GET /files/{id}/siblings' => [FileController::class, 'siblings'],
    'PUT /files/{id}' => [FileController::class, 'update'],
    'PUT /files/{id}/content' => [FileController::class, 'updateContent'],
    'DELETE /files/trash' => [FileController::class, 'emptyTrash'],
    'DELETE /files/{id}' => [FileController::class, 'delete'],
    'POST /files/{id}/move' => [FileController::class, 'move'],
    'POST /files/{id}/copy' => [FileController::class, 'copy'],
    'POST /files/batch-download' => [FileController::class, 'batchDownload'],
    'GET /files/download-zip' => [FileController::class, 'downloadZip'],
    'POST /files/{id}/rename' => [FileController::class, 'rename'],
    'POST /files/{id}/star' => [FileController::class, 'toggleStar'],
    'POST /files/{id}/trash' => [FileController::class, 'trash'],
    'POST /files/{id}/restore' => [FileController::class, 'restore'],

    // Folders
    'POST /folders' => [FolderController::class, 'create'],

    // Versions
    'GET /files/{id}/versions' => [VersionController::class, 'listItems'],
    'POST /files/{id}/versions/{vid}/restore' => [VersionController::class, 'restore'],
    'DELETE /files/{id}/versions/{vid}' => [VersionController::class, 'delete'],

    // Sharing
    'POST /shares' => [ShareController::class, 'create'],
    'GET /shared' => [ShareController::class, 'manage'],
    'PUT /shares/{id}' => [ShareController::class, 'update'],
    'DELETE /shares/{id}' => [ShareController::class, 'delete'],
    'GET /s/{token}' => [ShareController::class, 'access'],
    'GET /s/{token}/download' => [ShareController::class, 'download'],
    'GET /api/shares' => [ShareController::class, 'listItems'],

    // Settings
    'GET /settings' => [SettingsController::class, 'profile'],
    'PUT /settings' => [SettingsController::class, 'update'],
    'POST /settings/password' => [SettingsController::class, 'changePassword'],

    // Admin
    'GET /admin/users' => [AdminController::class, 'users'],
    'POST /admin/users' => [AdminController::class, 'createUser'],
    'DELETE /admin/users/{id}' => [AdminController::class, 'deleteUser'],
    'PUT /admin/users/{id}' => [AdminController::class, 'updateUser'],
    'GET /admin/settings' => [AdminController::class, 'settings'],
    'PUT /admin/settings' => [AdminController::class, 'updateSettings'],

    // API
    'GET /api/files' => [FileController::class, 'apiList'],
    'GET /api/stats' => [FileController::class, 'stats'],
];
