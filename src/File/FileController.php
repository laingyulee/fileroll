<?php

declare(strict_types=1);

namespace FileRoll\File;

use FileRoll\Core\Config;
use FileRoll\Core\ControllerTrait;
use FileRoll\Core\Request;
use FileRoll\Core\Response;
use FileRoll\Core\Container;
use FileRoll\Database\Connection;
use FileRoll\User\UserRepository;

class FileController
{
    use ControllerTrait;
    public function index(Request $request, Response $response): Response
    {
        return $this->listItems($request, $response, []);
    }

    public function listItems(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $parentId = $request->query('folder') !== null ? (int) $request->query('folder') : null;
        $orderBy = $request->query('sort', 'name');
        $direction = $request->query('dir', 'ASC');
        $isStarred = $request->query('starred') === '1';
        $isTrash = $request->query('trash') === '1';

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);
        $userRepo = $c->get(UserRepository::class);

        if ($isStarred) {
            $files = $fileRepo->listStarred($userId);
        } elseif ($isTrash) {
            $files = $fileRepo->listTrashed($userId);
        } else {
            $files = $fileRepo->listByParent($parentId, $userId, $orderBy, $direction);
        }

        $user = $userRepo->findById($userId);
        $storageUsed = $fileRepo->getStorageUsed($userId);
        $storageQuota = $user?->storageQuota ?? 0;
        $breadcrumb = [];

        if ($parentId !== null && !$isStarred && !$isTrash) {
            $breadcrumb = $fileRepo->getPath($parentId);
        }

        if ($request->isAjax()) {
            $fileData = array_map(fn($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'mime_type' => $f->mimeType,
                'size' => $f->size,
                'formatted_size' => $f->getFormattedSize(),
                'is_folder' => $f->isFolder,
                'is_starred' => $f->isStarred,
                'icon' => $f->getIcon(),
                'created_at' => $f->createdAt,
                'updated_at' => $f->updatedAt,
            ], $files);

            return $response->json([
                'files' => $fileData,
                'current_folder' => $parentId,
                'storage_used' => $storageUsed,
                'storage_quota' => $user?->storageQuota ?? 0,
            ]);
        }

        $currentPage = $isStarred ? 'starred' : ($isTrash ? 'trash' : 'files');

        $trashAutoClean = '0';
        $trashGraceDays = 30;
        if ($isTrash) {
            $settingsRepo = $c->get(\FileRoll\Settings\SettingsRepository::class);
            $trashAutoClean = $settingsRepo->get('trash_auto_clean', '0');
            $trashGraceDays = (int) $settingsRepo->get('trash_grace_days', '30');
        }

        ob_start();
        $template = __DIR__ . '/../../templates/files/list.php';
        include $template;
        $html = ob_get_clean();

        return $response->html($html);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $name = $request->input('name', '');
        $rawParentId = $request->input('parent_id');
        $parentId = ($rawParentId !== null && $rawParentId !== '' && $rawParentId !== 'null' && (int) $rawParentId > 0)
            ? (int) $rawParentId
            : null;

        if ($name === '') {
            return Response::error(400, t('error.file_name_required'));
        }

        $c = Container::getInstance();
        $fileService = $c->get(\FileRoll\File\FileService::class);

        try {
            $file = $fileService->createTextFile($userId, $parentId, $name);

            return $response->json([
                'success' => true,
                'file' => [
                    'id' => $file->id,
                    'name' => $file->name,
                    'mime_type' => $file->mimeType,
                    'size' => $file->size,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return Response::error(400, $e->getMessage());
        }
    }

    public function upload(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $rawFolderId = $request->input('folder_id');
        $parentId = ($rawFolderId !== null && $rawFolderId !== '' && $rawFolderId !== 'null' && (int) $rawFolderId > 0)
            ? (int) $rawFolderId
            : null;

        $files = $request->uploadedFiles();
        if (empty($files['file'])) {
            return Response::error(400, t('error.no_file_uploaded'));
        }

        $uploadedFile = $files['file'];
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => t('error.upload_ini_size'),
                UPLOAD_ERR_FORM_SIZE => t('error.upload_form_size'),
                UPLOAD_ERR_PARTIAL => t('error.upload_partial'),
                UPLOAD_ERR_NO_FILE => t('error.upload_no_file'),
                UPLOAD_ERR_NO_TMP_DIR => t('error.upload_no_tmp_dir'),
                UPLOAD_ERR_CANT_WRITE => t('error.upload_cant_write'),
                UPLOAD_ERR_EXTENSION => t('error.upload_extension'),
            ];
            $errorMsg = $errors[$uploadedFile['error']] ?? t('error.upload_unknown');
            return Response::error(400, $errorMsg);
        }

        $maxSize = Container::getInstance()->get(\FileRoll\Core\Config::class)->get('security.max_upload_size', 0);
        if ($maxSize > 0 && $uploadedFile['size'] > $maxSize) {
            return Response::error(413, t('error.upload_too_large'));
        }

        try {
            $c = Container::getInstance();
            $fileService = $c->get(\FileRoll\File\FileService::class);

            $fileEntity = $fileService->upload(
                $userId,
                $parentId,
                $uploadedFile['tmp_name'],
                $uploadedFile['name']
            );

            if ($request->isAjax()) {
                return $response->json([
                    'success' => true,
                    'file' => [
                        'id' => $fileEntity->id,
                        'name' => $fileEntity->name,
                        'size' => $fileEntity->size,
                        'mime_type' => $fileEntity->mimeType,
                        'icon' => $fileEntity->getIcon(),
                    ],
                ]);
            }

            return $response->redirect(BASE_URL . '/files?folder=' . ($parentId ?? ''));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::error(400, $e->getMessage());
            }
            return $response->redirect(BASE_URL . '/files?folder=' . ($parentId ?? '') . '&error=' . urlencode($e->getMessage()));
        }
    }

    public function download(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);
        $storage = $c->get(Storage::class);

        $file = $fileRepo->findByIdAndOwnerAny($fileId, $userId);
        if ($file === null || $file->isFolder || $file->contentHash === null) {
            return Response::error(404, t('error.file_not_found'));
        }

        $filePath = $storage->getPath($file->contentHash);
        if ($filePath === null) {
            return Response::error(404, t('error.content_not_found'));
        }

        header('Content-Type: ' . ($file->mimeType ?: 'application/octet-stream'));
        header('Content-Length: ' . $file->size);
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $file->name) . '"');
        header('Cache-Control: no-cache');

        readfile($filePath);
        exit;
    }

    public function preview(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);
        $storage = $c->get(Storage::class);

        $file = $fileRepo->findByIdAndOwnerAny($fileId, $userId);
        if ($file === null) {
            return Response::error(404, t('error.file_not_found'));
        }
        if ($file->isFolder) {
            return Response::error(404, t('error.folder_not_previewable'));
        }
        if ($file->contentHash === null) {
            return Response::error(404, t('error.content_not_found'));
        }

        $filePath = $storage->getPath($file->contentHash);
        if ($filePath === null) {
            return Response::error(404, t('error.content_not_found'));
        }

        if ($file->mimeType === 'image/svg+xml') {
            $response->header('Content-Type', 'text/plain; charset=utf-8');
            $response->header('Content-Disposition', 'inline');
            $response->header('X-Content-Type-Options', 'nosniff');
            return $response->body(file_get_contents($filePath));
        }

        if ($file->isImage() || $file->isVideo() || $file->isAudio() || $file->isPdf()) {
            header('Content-Type: ' . $file->mimeType);
            header('Content-Length: ' . $file->size);
            header('Content-Disposition: inline; filename="' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $file->name) . '"');
            header('Cache-Control: private, max-age=3600');
            readfile($filePath);
            exit;
        }

        if ($file->isText()) {
            $content = file_get_contents($filePath);
            $response->header('Content-Type', 'text/plain; charset=utf-8');
            return $response->body($content);
        }

        return $this->download($request, $response, $params);
    }

    public function siblings(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $db = Container::getInstance()->get(Connection::class);
        $fileRepo = new FileRepository($db);

        $file = $fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return $response->json(['images' => []]);
        }

        $parentId = $file->parentId;
        $imageExts = ['jpg','jpeg','png','gif','webp','svg','bmp','ico','tiff'];
        $files = $fileRepo->listByParentWithExtensions($parentId, $userId, $imageExts);

        $images = array_map(fn($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'size' => $f->size,
        ], $files);

        return $response->json(['images' => $images]);
    }

    public function update(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);

        $file = $fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null || $file->isFolder || $file->contentHash === null) {
            return Response::error(404, t('error.file_not_found'));
        }

        $data = $request->all();
        $allowed = ['name', 'is_starred'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if (!empty($filtered)) {
            $fileRepo->update($fileId, $filtered);
        }

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/files?folder=' . ($file->parentId ?? ''));
    }

    public function updateContent(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);
        $fileService = $c->get(\FileRoll\File\FileService::class);
        $storage = $c->get(Storage::class);

        $file = $fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null || $file->isFolder) {
            return Response::error(404, t('error.file_not_found'));
        }

        if (!$this->isEditableMimeType($file->mimeType)) {
            return Response::error(400, t('error.not_editable_file_type'));
        }

        $body = file_get_contents('php://input');
        $content = $body !== false ? $body : '';

        $maxSize = Container::getInstance()->get(\FileRoll\Core\Config::class)->get('security.max_upload_size', 0);
        if ($maxSize > 0 && strlen($content) > $maxSize) {
            return Response::error(413, t('error.upload_too_large'));
        }

        $tempPath = $storage->getTempPath($file->name);
        file_put_contents($tempPath, $content);

        $fileService->upload($userId, $file->parentId, $tempPath, $file->name);

        return $response->json(['success' => true]);
    }

    public function delete(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);
        $fileService = $c->get(\FileRoll\File\FileService::class);

        $file = $fileRepo->findByIdAndOwnerAny($fileId, $userId);
        if ($file === null) {
            return Response::error(404, t('error.file_not_found'));
        }

        $parentFolder = $file->parentId;

        $result = $fileService->delete($fileId, $userId);

        if ($request->isAjax()) {
            return $response->json($result);
        }

        if ($result['missing'] ?? false) {
            return $response->redirect(BASE_URL . '/files?folder=' . ($parentFolder ?? '') . '&notice=missing_cleaned');
        }

        return $response->redirect(BASE_URL . '/files?folder=' . ($parentFolder ?? ''));
    }

    public function move(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);
        $targetFolderId = $request->input('target_folder_id') !== null
            ? (int) $request->input('target_folder_id')
            : null;

        $c = Container::getInstance();
        $fileService = $c->get(\FileRoll\File\FileService::class);

        try {
            $fileService->move($fileId, $userId, $targetFolderId);
            return $response->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return Response::error(400, $e->getMessage());
        }
    }

    public function copy(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);
        $targetFolderId = $request->input('target_folder_id') !== null
            ? (int) $request->input('target_folder_id')
            : null;

        $c = Container::getInstance();
        $fileService = $c->get(\FileRoll\File\FileService::class);

        try {
            $newFile = $fileService->copy($fileId, $userId, $targetFolderId);
            if ($newFile === null) {
                return Response::error(404, t('error.file_not_found'));
            }
            return $response->json(['success' => true, 'file_id' => $newFile->id]);
        } catch (\RuntimeException $e) {
            return Response::error(400, $e->getMessage());
        }
    }

    public function rename(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);
        $newName = $request->input('name', '');

        if ($newName === '') {
            return Response::error(400, t('error.name_required'));
        }

        $c = Container::getInstance();
        $fileService = $c->get(\FileRoll\File\FileService::class);

        try {
            $fileService->rename($fileId, $userId, $newName);
            return $response->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return Response::error(400, $e->getMessage());
        }
    }

    public function toggleStar(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $fileService = $c->get(\FileRoll\File\FileService::class);

        $fileService->toggleStar($fileId, $userId);
        return $response->json(['success' => true]);
    }

    public function emptyTrash(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);

        $c = Container::getInstance();
        $fileService = $c->get(\FileRoll\File\FileService::class);

        $count = $fileService->emptyTrash($userId);

        return $response->json(['success' => true, 'count' => $count]);
    }

    public function trash(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $fileService = $c->get(\FileRoll\File\FileService::class);

        $fileService->trash($fileId, $userId);

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/files');
    }

    public function restore(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $fileService = $c->get(\FileRoll\File\FileService::class);

        $fileService->restore($fileId, $userId);

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/files');
    }

    public function apiList(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $parentId = $request->query('folder') !== null ? (int) $request->query('folder') : null;
        $search = $request->query('search', '');

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);

        if ($search !== '') {
            $files = $fileRepo->search($search, $userId);
        } else {
            $files = $fileRepo->listByParent($parentId, $userId);
        }

        $fileData = array_map(fn($f) => $f->toArray(), $files);

        return $response->json(['files' => $fileData]);
    }

    public function stats(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);
        $userRepo = $c->get(UserRepository::class);

        $user = $userRepo->findById($userId);
        $storageUsed = $fileRepo->getStorageUsed($userId);
        $totalFiles = $fileRepo->countByOwner($userId);

        return $response->json([
            'storage_used' => $storageUsed,
            'storage_quota' => $user?->storageQuota ?? 0,
            'total_files' => $totalFiles,
        ]);
    }

    private function isEditableMimeType(string $mimeType): bool
    {
        if (str_starts_with($mimeType, 'text/')) {
            return true;
        }

        $editable = [
            'application/json',
            'application/javascript',
            'application/xml',
            'application/xhtml+xml',
            'application/x-httpd-php',
            'application/x-sh',
            'application/x-yaml',
            'application/sql',
        ];

        return in_array($mimeType, $editable, true);
    }

    public function batchDownload(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return Response::error(400, t('error.invalid_request'));
        }

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);
        $config = $c->get(Config::class);
        $maxSize = $config->get('files.batch_download_max_size', 100 * 1024 * 1024);

        $totalSize = 0;
        $items = [];
        foreach ($ids as $id) {
            $file = $fileRepo->findByIdAndOwner((int) $id, $userId);
            if ($file === null) {
                return Response::error(403, t('error.access_denied'));
            }
            $this->collectFilesForZip($file, $userId, $fileRepo, $items, $totalSize);
        }

        if ($totalSize > $maxSize) {
            return Response::error(413, t('files.batch_download_too_large', ['limit' => $this->formatBytes($maxSize)]));
        }

        if (empty($items)) {
            return Response::error(400, t('error.invalid_request'));
        }

        try {
            $zipPath = $this->createZip($items, $userId);
        } catch (\RuntimeException $e) {
            return Response::error(500, t('files.error'));
        }

        $token = bin2hex(random_bytes(16));
        $_SESSION['zip_tokens'][$token] = [
            'path' => $zipPath,
            'expires' => time() + 600,
        ];

        return $response->json(['download_url' => '/files/download-zip?token=' . $token]);
    }

    public function downloadZip(Request $request, Response $response): Response
    {
        $token = $request->query('token');
        if (empty($token) || !isset($_SESSION['zip_tokens'][$token])) {
            return Response::error(404, t('error.not_found'));
        }

        $info = $_SESSION['zip_tokens'][$token];
        if ($info['expires'] < time() || !file_exists($info['path'])) {
            unset($_SESSION['zip_tokens'][$token]);
            return Response::error(404, t('error.not_found'));
        }

        $zipPath = $info['path'];
        unset($_SESSION['zip_tokens'][$token]);

        $response->statusCode(200);
        $response->header('Content-Type', 'application/zip');
        $response->header('Content-Disposition', 'attachment; filename="fileroll-batch-download.zip"');
        $response->header('Content-Length', (string) filesize($zipPath));
        $response->body(file_get_contents($zipPath));

        return $response;
    }

    private function collectFilesForZip(FileEntity $file, int $userId, FileRepository $repo, array &$items, int &$totalSize, string $basePath = ''): void
    {
        if ($file->isFolder) {
            $children = $repo->listByParent($file->id, $userId);
            $path = $basePath === '' ? $file->name : $basePath . '/' . $file->name;
            foreach ($children as $child) {
                $this->collectFilesForZip($child, $userId, $repo, $items, $totalSize, $path);
            }
            return;
        }

        if ($file->contentHash === null) {
            return;
        }

        $items[] = [
            'file' => $file,
            'path' => $basePath === '' ? $file->name : $basePath . '/' . $file->name,
        ];
        $totalSize += (int) $file->size;
    }

    private function createZip(array $items, int $userId): string
    {
        $c = Container::getInstance();
        $storage = $c->get(Storage::class);

        $tempDir = __DIR__ . '/../../storage/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/batch_' . $userId . '_' . uniqid() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create zip archive');
        }

        foreach ($items as $item) {
            $content = $storage->get($item['file']->contentHash);
            if ($content !== null) {
                $zip->addFromString($item['path'], $content);
            }
        }

        $zip->close();
        return $zipPath;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}