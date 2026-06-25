<?php

declare(strict_types=1);

namespace FileRoll\Share;

use FileRoll\Core\ControllerTrait;

use FileRoll\Core\Request;
use FileRoll\Core\Response;
use FileRoll\Core\Container;
use FileRoll\Database\Connection;
use FileRoll\File\FileRepository;
use FileRoll\File\Storage;

class ShareController
{
    use ControllerTrait;
    public function create(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) $request->input('file_id', 0);
        $password = $request->input('password', '');
        $expiresIn = $request->input('expires_in', '');
        $maxDownloads = $request->input('max_downloads', '');
        $permissionLevel = $request->input('permission_level', 'read');

        if ($fileId === 0) {
            return Response::error(400, t('error.file_id_required'));
        }

        $c = Container::getInstance();
        $fileRepo = $c->get(FileRepository::class);
        $shareRepo = $c->get(ShareRepository::class);

        $file = $fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return Response::error(404, t('error.file_not_found'));
        }

        $token = bin2hex(random_bytes(32));
        $data = [
            'file_id' => (string) $fileId,
            'shared_by' => (string) $userId,
            'token' => $token,
            'permission_level' => $permissionLevel,
        ];

        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        if ($expiresIn !== '') {
            $hours = (int) $expiresIn;
            if ($hours > 0) {
                $data['expires_at'] = date('Y-m-d H:i:s', time() + ($hours * 3600));
            }
        }

        if ($maxDownloads !== '') {
            $max = (int) $maxDownloads;
            if ($max > 0) {
                $data['max_downloads'] = (string) $max;
            }
        }

        $share = $shareRepo->create($data);

        if ($request->isAjax()) {
            return $response->json([
                'success' => true,
                'share' => $share->toArray(),
                'url' => $share->getShareUrl(),
            ]);
        }

        return $response->redirect(BASE_URL . '/files?folder=' . ($file->parentId ?? ''));
    }

    public function access(Request $request, Response $response, array $params): Response
    {
        $token = $params['token'] ?? '';

        $c = Container::getInstance();
        $shareRepo = $c->get(ShareRepository::class);
        $fileRepo = $c->get(FileRepository::class);

        $share = $shareRepo->findByToken($token);
        if ($share === null || !$share->isValid()) {
            ob_start();
            $error = t('error.share_not_found');
            include __DIR__ . '/../../templates/errors/404.php';
            $html = ob_get_clean();
            return $response->html($html, 404);
        }

        $file = $fileRepo->findById($share->fileId);
        if ($file === null) {
            return Response::error(404, t('error.shared_file_not_found'));
        }

        if ($share->hasPassword() && $request->input('password', '') !== '') {
            if (!password_verify($request->input('password'), $share->passwordHash)) {
                $error = t('error.incorrect_password');
                ob_start();
                include __DIR__ . '/../../templates/files/share.php';
                $html = ob_get_clean();
                return $response->html($html);
            }
            $_SESSION['share_' . $token . '_authed'] = true;
        }

        if ($share->hasPassword() && empty($_SESSION['share_' . $token . '_authed'])) {
            ob_start();
            $error = '';
            include __DIR__ . '/../../templates/files/share.php';
            $html = ob_get_clean();
            return $response->html($html);
        }

        ob_start();
        $error = '';
        include __DIR__ . '/../../templates/files/share_access.php';
        $html = ob_get_clean();

        return $response->html($html);
    }

    public function download(Request $request, Response $response, array $params): Response
    {
        $token = $params['token'] ?? '';

        $c = Container::getInstance();
        $shareRepo = $c->get(ShareRepository::class);
        $fileRepo = $c->get(FileRepository::class);
        $storage = $c->get(Storage::class);

        $share = $shareRepo->findByToken($token);
        if ($share === null || !$share->isValid()) {
            return Response::error(404, t('error.share_not_found'));
        }

        if ($share->hasPassword() && empty($_SESSION['share_' . $token . '_authed'])) {
            return Response::error(403, t('error.password_required'));
        }

        $file = $fileRepo->findById($share->fileId);
        if ($file === null || $file->isFolder || $file->contentHash === null) {
            return Response::error(404, t('error.file_not_found'));
        }

        $filePath = $storage->getPath($file->contentHash);
        if ($filePath === null) {
            return Response::error(404, t('error.content_not_found'));
        }

        $shareRepo->incrementDownloadCount($share->id);

        header('Content-Type: ' . ($file->mimeType ?: 'application/octet-stream'));
        header('Content-Length: ' . $file->size);
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $file->name) . '"');
        header('Cache-Control: no-cache');

        readfile($filePath);
        exit;
    }

    public function delete(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $shareId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $shareRepo = $c->get(ShareRepository::class);

        $share = $shareRepo->findById($shareId);
        if ($share === null) {
            return Response::error(404, t('error.share_not_found'));
        }

        $fileRepo = $c->get(FileRepository::class);
        $file = $fileRepo->findById($share->fileId);
        if ($file === null || $file->ownerId !== $userId) {
            return Response::error(403, t('error.not_authorized'));
        }

        $shareRepo->delete($shareId);

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/files?folder=' . ($file->parentId ?? ''));
    }

    public function listItems(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);

        $c = Container::getInstance();
        $shareRepo = $c->get(ShareRepository::class);

        $shares = $shareRepo->findByOwnerId($userId);
        $shareData = array_map(fn($s) => $s->toArray(), $shares);

        return $response->json(['shares' => $shareData]);
    }

    public function manage(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);

        $c = Container::getInstance();
        $shareRepo = $c->get(ShareRepository::class);
        $fileRepo = $c->get(FileRepository::class);
        $userRepo = $c->get(\FileRoll\User\UserRepository::class);

        $user = $userRepo->findById($userId);
        $storageUsed = $fileRepo->getStorageUsed($userId);
        $storageQuota = $user?->storageQuota ?? 0;

        $shares = $shareRepo->findByOwnerId($userId);

        $sharesWithFiles = [];
        foreach ($shares as $share) {
            $file = $fileRepo->findById($share->fileId);
            $sharesWithFiles[] = [
                'share' => $share,
                'file' => $file,
            ];
        }

        $currentPage = 'shared';
        $success = $_GET['success'] ?? '';

        ob_start();
        $csrf = Container::getInstance()->get('auth.csrf');
        include __DIR__ . '/../../templates/files/shared.php';
        $html = ob_get_clean();

        return $response->html($html);
    }

    public function update(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $shareId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $shareRepo = $c->get(ShareRepository::class);

        $share = $shareRepo->findById($shareId);
        if ($share === null) {
            if ($request->isAjax()) {
                return Response::error(404, t('error.share_not_found'));
            }
            return $response->redirect(BASE_URL . '/shared');
        }

        $fileRepo = $c->get(FileRepository::class);
        $file = $fileRepo->findById($share->fileId);
        if ($file === null || $file->ownerId !== $userId) {
            if ($request->isAjax()) {
                return Response::error(403, t('error.not_authorized'));
            }
            return $response->redirect(BASE_URL . '/shared');
        }

        $data = [];
        if ($request->input('permission_level') !== null) {
            $level = $request->input('permission_level');
            if (in_array($level, ['read', 'write'], true)) {
                $data['permission_level'] = $level;
            }
        }
        if ($request->input('expires_in') !== null) {
            $hours = (int) $request->input('expires_in');
            $data['expires_at'] = $hours > 0 ? date('Y-m-d H:i:s', time() + ($hours * 3600)) : null;
        }
        if ($request->input('max_downloads') !== null) {
            $max = (int) $request->input('max_downloads');
            $data['max_downloads'] = $max > 0 ? $max : null;
        }
        if ($request->input('is_active') !== null) {
            $data['is_active'] = $request->input('is_active') ? '1' : '0';
        }

        if (!empty($data)) {
            $shareRepo->update($shareId, $data);
        }

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/shared');
    }
}