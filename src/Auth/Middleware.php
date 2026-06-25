<?php

declare(strict_types=1);

namespace FileRoll\Auth;

use FileRoll\Core\Request;
use FileRoll\Core\Response;
use FileRoll\Core\Container;

class Middleware
{
    public function requireAuth(Request $request, callable $next): Response
    {
        $session = Container::getInstance()->get('auth.session');

        if (!$session->isLoggedIn()) {
            return $this->unauthResponse($request);
        }

        $userId = $session->validate();
        if ($userId === null) {
            return $this->unauthResponse($request);
        }

        $request->attributes['user_id'] = $userId;

        return $next($request);
    }

    private function unauthResponse(Request $request): Response
    {
        if ($this->expectsApiAuth($request)) {
            $response = Response::error(401, t('error.auth_required'));
            $response->header('WWW-Authenticate', 'Basic realm="FileRoll"');
            return $response;
        }

        if ($request->isAjax() || $request->isJson()) {
            return Response::error(401, t('error.auth_required'));
        }

        return (new Response())->redirect(BASE_URL . '/login');
    }

    private function expectsApiAuth(Request $request): bool
    {
        $ua = $request->getUserAgent();
        if (str_contains($ua, 'ownCloud') || str_contains($ua, 'mirall') || str_contains($ua, 'WebDAV')) {
            return true;
        }

        $webdavMethods = ['PROPFIND', 'PROPPATCH', 'MKCOL', 'COPY', 'MOVE', 'LOCK', 'UNLOCK'];
        if (in_array($request->method(), $webdavMethods, true)) {
            return true;
        }

        $accept = $request->header('Accept', '');
        if ($request->header('Authorization') || str_contains($accept, 'application/json')) {
            return true;
        }

        return false;
    }

    public function requireAdmin(Request $request, callable $next): Response
    {
        $userId = $request->attributes['user_id'] ?? null;

        if ($userId === null) {
            return Response::error(401, t('error.auth_required'));
        }

        $db = Container::getInstance()->get(\FileRoll\Database\Connection::class);
        $user = $db->fetch('SELECT role FROM users WHERE id = ?', [$userId]);

        if ($user === null || $user['role'] !== 'admin') {
            return Response::error(403, t('error.admin_required'));
        }

        $request->attributes['is_admin'] = true;

        return $next($request);
    }

    public function verifyCsrf(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $csrf = Container::getInstance()->get('auth.csrf');
        if (!$csrf->validateRequest($request)) {
            return Response::error(403, t('error.invalid_csrf'));
        }

        return $next($request);
    }
}
