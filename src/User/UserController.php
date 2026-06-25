<?php

declare(strict_types=1);

namespace FileRoll\User;

use FileRoll\Core\ControllerTrait;

use FileRoll\Core\Config;
use FileRoll\Core\Request;
use FileRoll\Core\Response;
use FileRoll\Core\Container;
use FileRoll\User\UserRepository;

class UserController
{
    use ControllerTrait;
    private ?Config $config = null;

    private function getConfig(): Config
    {
        if ($this->config === null) {
            $this->config = Container::getInstance()->get(Config::class);
        }
        return $this->config;
    }
    public function status(Request $request, Response $response): Response
    {
        return $response->json([
            'installed' => true,
            'maintenance' => false,
            'needs_db_upgrade' => false,
            'version' => '10.15.0.0',
            'versionstring' => 'ownCloud 10.15.0',
            'edition' => '',
            'productname' => 'ownCloud',
            'product' => 'ownCloud',
            'productversion' => '10.15.0',
        ]);
    }

    public function capabilities(Request $request, Response $response): Response
    {
        $format = $request->query('format', 'json');

        $data = [
            'ocs' => [
                'meta' => [
                    'status' => 'ok',
                    'statuscode' => 200,
                    'message' => 'OK',
                ],
                'data' => [
                    'version' => [
                        'major' => 10,
                        'minor' => 15,
                        'micro' => 0,
                        'string' => '10.15.0',
                        'edition' => '',
                        'product' => 'ownCloud',
                        'productversion' => '10.15.0',
                    ],
                    'capabilities' => [
                        'core' => [
                            'webdavroot' => '/dav',
                            'pollinterval' => 60,
                            'status' => [
                                'installed' => true,
                                'maintenance' => false,
                                'needsDbUpgrade' => false,
                                'version' => '10.15.0.0',
                                'versionstring' => 'ownCloud 10.15.0',
                                'edition' => '',
                                'productname' => 'ownCloud',
                                'productversion' => '10.15.0',
                            ],
                        ],
                        'dav' => [
                            'chunking' => '1.0',
                        ],
                        'files_sharing' => [
                            'api_enabled' => true,
                            'public' => [
                                'enabled' => true,
                                'upload' => true,
                                'send_mail' => false,
                            ],
                            'user' => [
                                'send_mail' => false,
                            ],
                            'resharing' => true,
                        ],
                        'files' => [
                            'bigfilechunking' => true,
                            'undelete' => true,
                            'versioning' => true,
                        ],
                        'spaces' => [
                            'enabled' => false,
                        ],
                    ],
                ],
            ],
        ];

        return $response->json($data);
    }

    public function cloudUser(Request $request, Response $response): Response
    {
        $authHeader = $request->header('Authorization', '');
        if (!str_starts_with($authHeader, 'Basic ')) {
            $r = Response::error(401, t('error.auth_required'));
            $r->header('WWW-Authenticate', 'Basic realm="FileRoll"');
            return $r;
        }

        $decoded = base64_decode(substr($authHeader, 6), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return Response::error(401, t('auth.invalid_credentials'));
        }

        [$username, $password] = explode(':', $decoded, 2);

        $c = Container::getInstance();
        $userRepo = $c->get(UserRepository::class);
        $passwordAuth = $c->get('auth.password');

        $user = $userRepo->findByUsername($username);
        if ($user === null || !$passwordAuth->verify($password, $user->passwordHash)) {
            $r = Response::error(401, t('auth.invalid_credentials'));
            $r->header('WWW-Authenticate', 'Basic realm="FileRoll"');
            return $r;
        }

        if (!$user->isActive) {
            return Response::error(403, t('auth.account_disabled'));
        }

        $data = [
            'ocs' => [
                'meta' => [
                    'status' => 'ok',
                    'statuscode' => 200,
                    'message' => 'OK',
                ],
                'data' => [
                    'enabled' => 'true',
                    'id' => (string) $user->id,
                    'display-name' => $user->displayName ?: $user->username,
                    'email' => $user->email,
                    'language' => '',
                    'home' => (defined('BASE_URL') ? BASE_URL : '') . '/dav/files/' . $user->username . '/',
                ],
            ],
        ];

        return $response->json($data);
    }

    public function graphDrives(Request $request, Response $response): Response
    {
        // Authenticate via Basic Auth
        $authHeader = $request->header('Authorization', '');
        if (!str_starts_with($authHeader, 'Basic ')) {
            $r = Response::error(401, 'Unauthorized');
            $r->header('WWW-Authenticate', 'Basic realm="FileRoll"');
            return $r;
        }

        $decoded = base64_decode(substr($authHeader, 6), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return Response::error(401, 'Invalid credentials');
        }

        [$username, $password] = explode(':', $decoded, 2);

        $c = Container::getInstance();
        $userRepo = $c->get(UserRepository::class);
        $passwordAuth = $c->get('auth.password');

        $user = $userRepo->findByUsername($username);
        if ($user === null || !$passwordAuth->verify($password, $user->passwordHash)) {
            $r = Response::error(401, 'Invalid credentials');
            $r->header('WWW-Authenticate', 'Basic realm="FileRoll"');
            return $r;
        }

        if (!$user->isActive) {
            return Response::error(403, 'Account disabled');
        }

        $fileRepo = $c->get(\FileRoll\File\FileRepository::class);
        $storageUsed = $fileRepo->getStorageUsed($user->id);
        $quota = $user->storageQuota ?? 107374182400;
        $baseUrl = (defined('BASE_URL') ? BASE_URL : '');

        $data = [
            'value' => [
                [
                    'driveAlias' => 'personal',
                    'driveType' => 'personal',
                    'id' => (string) $user->id . '$' . $user->id,
                    'name' => 'Personal',
                    'owner' => [
                        'user' => [
                            'displayName' => $user->displayName ?: $user->username,
                    'id' => $user->username,
                        ],
                    ],
                    'quota' => [
                        'remaining' => max(0, $quota - $storageUsed),
                        'state' => $storageUsed >= $quota ? 'full' : 'normal',
                        'total' => $quota,
                        'used' => $storageUsed,
                    ],
                    'root' => [
                        'webDavUrl' => ($request->server('HTTPS') ? 'https' : 'http') . '://' . $request->server('HTTP_HOST') . $baseUrl . '/dav/files/' . $user->username . '/',
                    ],
                ],
            ],
        ];

        return $response->json($data);
    }

    public function login(Request $request, Response $response): Response
    {
        $csrf = Container::getInstance()->get('auth.csrf');

        ob_start();
        $error = $request->query('error', '');
        include __DIR__ . '/../../templates/auth/login.php';
        $html = ob_get_clean();

        return $response->html($html);
    }

    public function authenticate(Request $request, Response $response): Response
    {
        $username = trim($request->input('username', ''));
        $password = $request->input('password', '');

        if ($username === '' || $password === '') {
            return $response->redirect(BASE_URL . '/login?error=' . urlencode(\FileRoll\Core\I18n::getInstance()->t('auth.username_required')));
        }

        $ip = $request->getIp();
        if ($this->isRateLimited($ip)) {
            return $response->redirect(BASE_URL . '/login?error=' . urlencode(\FileRoll\Core\I18n::getInstance()->t('auth.too_many_attempts')));
        }

        $c = Container::getInstance();
        $userRepo = $c->get(UserRepository::class);
        $passwordAuth = $c->get('auth.password');

        $user = $userRepo->findByUsername($username);
        if ($user === null || !$passwordAuth->verify($password, $user->passwordHash)) {
            $this->recordFailedAttempt($ip);
            return $response->redirect(BASE_URL . '/login?error=' . urlencode(\FileRoll\Core\I18n::getInstance()->t('auth.invalid_credentials')));
        }

        if (!$user->isActive) {
            return $response->redirect(BASE_URL . '/login?error=' . urlencode(\FileRoll\Core\I18n::getInstance()->t('auth.account_disabled')));
        }

        $this->clearFailedAttempts($ip);

        $session = Container::getInstance()->get('auth.session');
        $session->start($user->id, $request->getIp(), $request->getUserAgent());
        $userRepo->updateLastLogin($user->id);

        return $response->redirect(BASE_URL . '/');
    }

    private function getRateLimitFile(string $ip): string
    {
        return __DIR__ . '/../../storage/rate_limit_' . hash('sha256', $ip) . '.json';
    }

    private function isRateLimited(string $ip): bool
    {
        $file = $this->getRateLimitFile($ip);
        if (!file_exists($file)) {
            return false;
        }

        $fh = fopen($file, 'r');
        if ($fh === false) {
            return false;
        }
        flock($fh, LOCK_SH);
        $content = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        $data = json_decode($content, true);
        if ($data === null) {
            return false;
        }

        $maxAttempts = $this->getConfig()->get('security.rate_limit_login', 5);
        $window = $this->getConfig()->get('security.rate_limit_window', 900);

        $attempts = array_filter($data['attempts'] ?? [], fn($t) => $t > time() - $window);
        return count($attempts) >= $maxAttempts;
    }

    private function recordFailedAttempt(string $ip): void
    {
        $file = $this->getRateLimitFile($ip);
        $data = ['attempts' => []];

        $fh = fopen($file, 'c+');
        if ($fh === false) {
            return;
        }
        flock($fh, LOCK_EX);

        $content = stream_get_contents($fh);
        if ($content !== false && $content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $window = $this->getConfig()->get('security.rate_limit_window', 900);
        $data['attempts'][] = time();
        $data['attempts'] = array_values(array_filter($data['attempts'], fn($t) => $t > time() - $window));

        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, json_encode($data));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    private function clearFailedAttempts(string $ip): void
    {
        $file = $this->getRateLimitFile($ip);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        $session = Container::getInstance()->get('auth.session');
        $csrf = Container::getInstance()->get('auth.csrf');
        $session->destroy();
        $csrf->clearToken();

        return $response->redirect(BASE_URL . '/login');
    }

    public function profile(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);

        $c = Container::getInstance();
        $userRepo = $c->get(UserRepository::class);
        $fileRepo = $c->get(\FileRoll\File\FileRepository::class);

        $user = $userRepo->findById($userId);
        $storageUsed = $fileRepo->getStorageUsed($userId);
        $storageQuota = $user->storageQuota ?? 0;

        ob_start();
        $csrf = Container::getInstance()->get('auth.csrf');
        include __DIR__ . '/../../templates/settings/profile.php';
        $html = ob_get_clean();

        return $response->html($html);
    }

    public function update(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);

        $c = Container::getInstance();
        $userRepo = $c->get(UserRepository::class);

        $displayName = $request->input('display_name', '');
        $email = $request->input('email', '');
        $language = $request->input('language', '');

        $errors = [];
        if ($displayName !== '' && strlen($displayName) > 128) {
            $errors[] = \FileRoll\Core\I18n::getInstance()->t('settings.display_name_too_long');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = \FileRoll\Core\I18n::getInstance()->t('settings.invalid_email');
        }
        if ($email !== '' && $userRepo->existsByEmail($email, $userId)) {
            $errors[] = \FileRoll\Core\I18n::getInstance()->t('settings.email_in_use');
        }

        if (!empty($errors)) {
            return $response->redirect(BASE_URL . '/settings?error=' . urlencode(implode(', ', $errors)));
        }

        $data = [];
        if ($displayName !== '') $data['display_name'] = $displayName;
        if ($email !== '') $data['email'] = $email;
        if ($language !== '' && in_array($language, ['en','zh','ja','ko','it','de','fr','es'], true)) {
            $data['language'] = $language;
        }

        if (!empty($data)) {
            $userRepo->update($userId, $data);
        }

        if ($language !== '') {
            \FileRoll\Core\I18n::getInstance()->setLocale($language);
        }

        return $response->redirect(BASE_URL . '/settings?success=1');
    }

    public function changePassword(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $i18n = \FileRoll\Core\I18n::getInstance();

        $currentPassword = $request->input('current_password', '');
        $newPassword = $request->input('new_password', '');
        $confirmPassword = $request->input('confirm_password', '');

        if ($currentPassword === '' || $newPassword === '') {
            return $response->redirect(BASE_URL . '/settings?error=' . urlencode($i18n->t('settings.passwords_required')));
        }

        if ($newPassword !== $confirmPassword) {
            return $response->redirect(BASE_URL . '/settings?error=' . urlencode($i18n->t('settings.passwords_mismatch')));
        }

        if (strlen($newPassword) < 8) {
            return $response->redirect(BASE_URL . '/settings?error=' . urlencode($i18n->t('settings.password_too_short')));
        }

        $c = Container::getInstance();
        $userRepo = $c->get(UserRepository::class);
        $passwordAuth = $c->get('auth.password');

        $user = $userRepo->findById($userId);
        if ($user === null || !$passwordAuth->verify($currentPassword, $user->passwordHash)) {
            return $response->redirect(BASE_URL . '/settings?error=' . urlencode($i18n->t('settings.current_password_wrong')));
        }

        $newHash = $passwordAuth->hash($newPassword);
        $userRepo->updatePassword($userId, $newHash);

        return $response->redirect(BASE_URL . '/settings?success=password');
    }

    public function switchLanguage(Request $request, Response $response): Response
    {
        $language = $request->input('language', '');
        $supported = \FileRoll\Core\I18n::getInstance()->getAvailableLocales();

        if ($language === 'auto') {
            unset($_SESSION['language']);

            $userId = $_SESSION['user_id'] ?? null;
            if ($userId !== null) {
                try {
                    $c = Container::getInstance();
                    $userRepo = $c->get(UserRepository::class);
                    $userRepo->update($userId, ['language' => '']);
                } catch (\Throwable $e) { error_log('Failed to reset user language: ' . $e->getMessage()); }
            }
        } elseif ($language !== '' && isset($supported[$language])) {
            $_SESSION['language'] = $language;

            $userId = $_SESSION['user_id'] ?? null;
            if ($userId !== null) {
                try {
                    $c = Container::getInstance();
                    $userRepo = $c->get(UserRepository::class);
                    $userRepo->update($userId, ['language' => $language]);
                } catch (\Throwable $e) { error_log('Failed to save user language: ' . $e->getMessage()); }
            }
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '') {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $currentHost = $request->server('HTTP_HOST');
            if ($refererHost === null || $refererHost !== $currentHost) {
                $referer = '';
            }
        }
        if ($referer === '') {
            $referer = BASE_URL . '/login';
        }
        $separator = str_contains($referer, '?') ? '&' : '?';
        return $response->redirect($referer . $separator . 'lang_success=1');
    }
}