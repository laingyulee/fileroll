<?php

declare(strict_types=1);

namespace FileRoll\Admin;

use FileRoll\Core\ControllerTrait;

use FileRoll\Core\Request;
use FileRoll\Core\Response;
use FileRoll\Core\Container;
use FileRoll\User\UserRepository;

class AdminController
{
    use ControllerTrait;
    public function settings(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);

        $c = Container::getInstance();
        $userRepo = $c->get(UserRepository::class);
        $settingsRepo = $c->get(\FileRoll\Settings\SettingsRepository::class);
        $fileRepo = $c->get(\FileRoll\File\FileRepository::class);

        $user = $userRepo->findById($userId);
        $storageUsed = $fileRepo->getStorageUsed($userId);
        $storageQuota = $user?->storageQuota ?? 0;
        $settings = $settingsRepo->all();
        $users = $userRepo->findAll();
        $currentUserId = $userId;

        $currentPage = 'admin';

        ob_start();
        $csrf = Container::getInstance()->get('auth.csrf');
        include __DIR__ . '/../../templates/admin/settings.php';
        $html = ob_get_clean();

        return $response->html($html);
    }

    public function users(Request $request, Response $response): Response
    {
        return $response->redirect(BASE_URL . '/admin/settings?tab=users');
    }

    public function createUser(Request $request, Response $response): Response
    {
        $username = trim($request->input('username', ''));
        $email = trim($request->input('email', ''));
        $password = $request->input('password', '');
        $role = $request->input('role', 'user');
        $quota = (int) $request->input('quota', 107374182400);

        $errors = [];
        if ($username === '') $errors[] = t('admin.username_required');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('admin.email_required');
        if ($password === '') $errors[] = t('admin.password_required');
        if (!in_array($role, ['admin', 'user', 'viewer'], true)) $errors[] = t('admin.invalid_role');

        if (empty($errors)) {
            $c = Container::getInstance();
            $userRepo = $c->get(UserRepository::class);

            if ($userRepo->existsByUsername($username)) {
                $errors[] = t('admin.username_exists');
            }
            if ($userRepo->existsByEmail($email)) {
                $errors[] = t('admin.email_exists');
            }
        }

        if (!empty($errors)) {
            if ($request->isAjax()) {
                return Response::error(400, implode(', ', $errors));
            }
            return $response->redirect(BASE_URL . '/admin/settings?tab=users&error=' . urlencode(implode(', ', $errors)));
        }

        $c = Container::getInstance();
        $userRepo = $c->get(UserRepository::class);
        $passwordAuth = Container::getInstance()->get('auth.password');

        $hash = $passwordAuth->hash($password);
        $userRepo->create($username, $email, $hash, $role, $quota);

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/admin/settings?tab=users&success=user-created');
    }

    public function deleteUser(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $targetId = (int) ($params['id'] ?? 0);

        if ($targetId === $userId) {
            return $this->adminError($request, $response, t('admin.cannot_delete_self'));
        }

        $c = Container::getInstance();
        $db = $c->get(\FileRoll\Database\Connection::class);
        $userRepo = $c->get(UserRepository::class);
        $fileRepo = $c->get(\FileRoll\File\FileRepository::class);
        $fileService = $c->get(\FileRoll\File\FileService::class);

        $target = $userRepo->findById($targetId);
        if ($target === null) {
            return $this->adminError($request, $response, t('admin.user_not_found'));
        }

        // Prevent deleting the last active admin
        if ($target->isAdmin() && $userRepo->countActiveAdmins() <= 1) {
            return $this->adminError($request, $response, t('admin.cannot_delete_last_admin'));
        }

        // Recursively delete all files, folders, versions and physical storage
        $userFiles = $fileRepo->listByParent(null, $targetId);
        foreach ($userFiles as $file) {
            $fileService->delete($file->id, $targetId);
        }

        $db->delete('shares', 'shared_by = ?', [$targetId]);
        $db->delete('sessions', 'user_id = ?', [$targetId]);
        $db->delete('audit_log', 'user_id = ?', [$targetId]);

        $userRepo->delete($targetId);

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/admin/settings?tab=users&success=deleted');
    }

    public function updateUser(Request $request, Response $response, array $params): Response
    {
        $targetId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $userRepo = $c->get(UserRepository::class);

        $target = $userRepo->findById($targetId);
        if ($target === null) {
            return $this->adminError($request, $response, t('admin.user_not_found'));
        }

        $data = [];
        if ($request->input('role') !== null) {
            $role = $request->input('role');
            if (in_array($role, ['admin', 'user', 'viewer'], true)) {
                $data['role'] = $role;
            }
        }
        if ($request->input('is_active') !== null) {
            $data['is_active'] = $request->input('is_active') ? '1' : '0';
        }
        if ($request->input('storage_quota') !== null) {
            $data['storage_quota'] = (string) (int) $request->input('storage_quota');
        }

        // Prevent demoting or disabling the last active admin
        if ($target->isAdmin() && $userRepo->countActiveAdmins() <= 1) {
            $wouldLoseAdmin = isset($data['role']) && $data['role'] !== 'admin';
            $wouldDisable = isset($data['is_active']) && $data['is_active'] === '0';
            if ($wouldLoseAdmin || $wouldDisable) {
                return $this->adminError($request, $response, t('admin.cannot_disable_last_admin'));
            }
        }

        if (!empty($data)) {
            $userRepo->update($targetId, $data);
        }

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/admin/settings?tab=users&success=updated');
    }

    public function updateSettings(Request $request, Response $response): Response
    {
        $c = Container::getInstance();
        $settingsRepo = $c->get(\FileRoll\Settings\SettingsRepository::class);

        $data = $request->input('settings', []);
        if (is_string($data)) {
            parse_str($data, $data);
        }

        $allowed = ['site_name', 'trash_auto_clean', 'trash_grace_days', 'session_lifetime'];
        $allowedLifetimes = [7200, 86400, 2592000, 31536000, 3153600000];
        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            if ($key === 'session_lifetime') {
                $lifetime = (int) $value;
                if (!in_array($lifetime, $allowedLifetimes, true)) {
                    return $this->adminError($request, $response, t('admin.invalid_session_lifetime'));
                }
                $settingsRepo->set($key, (string) $lifetime);
                continue;
            }
            $settingsRepo->set($key, trim($value));
        }

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/admin/settings?success=1');
    }

    private function adminError(Request $request, Response $response, string $message): Response
    {
        if ($request->isAjax()) {
            return Response::error(400, $message);
        }
        return $response->redirect(BASE_URL . '/admin/settings?tab=users&error=' . urlencode($message));
    }
}