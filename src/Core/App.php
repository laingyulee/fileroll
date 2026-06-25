<?php

declare(strict_types=1);

namespace FileRoll\Core;

use FileRoll\Database\Connection;

class App
{
    private Container $container;
    private Router $router;
    private Config $config;

    public function __construct()
    {
        $this->container = new Container();
        $this->router = new Router();
        $this->config = new Config();
    }

    public function bootstrap(): void
    {
        Container::setInstance($this->container);

        $configPath = __DIR__ . '/../../config/config.php';
        if (!file_exists($configPath)) {
            $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            $appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
            if (str_starts_with($appRoot, $docRoot)) {
                $baseUrl = substr($appRoot, strlen($docRoot));
            } else {
                $baseUrl = '';
            }
            $tmpBaseUrl = $baseUrl !== '' ? $baseUrl : '';
            $installerPath = __DIR__ . '/../../install/index.php';
            if (file_exists($installerPath)) {
                header('Location: ' . $tmpBaseUrl . '/install/');
                exit;
            }
            die('Application not configured. Run the installer.');
        }

        $this->config = Config::fromFile($configPath);
        $this->container->set(Config::class, $this->config);

        if (!defined('BASE_URL')) {
            $configuredUrl = $this->config->get('app.url', '');
            if ($configuredUrl !== '' && $configuredUrl !== '/') {
                define('BASE_URL', rtrim($configuredUrl, '/'));
            } else {
                $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
                $appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
                if (str_starts_with($appRoot, $docRoot)) {
                    $baseUrl = substr($appRoot, strlen($docRoot));
                } else {
                    $baseUrl = '';
                }
                define('BASE_URL', $baseUrl !== '' ? $baseUrl : '');
            }
        }

        $errorHandler = new ErrorHandler($this->config->get('app.debug', false));
        $errorHandler->register();
        $this->container->set(ErrorHandler::class, $errorHandler);

        $this->setupDatabase();
        $this->setupSession();
        $this->setupI18n();
        $this->registerServices();
        $this->registerRoutes();

        $middleware = $this->config->get('app.middleware', []);
        foreach ($middleware as $mw) {
            $this->router->middleware([$mw]);
        }
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $response = new Response();

        $uri = $request->uri();
        $base = BASE_URL;
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        $request = $request->withUri($uri);

        $route = $this->router->match($request->method(), $request->uri());

        if ($route === null) {
            $path = $request->path();
            if ($path === '/dav' || str_starts_with($path, '/dav/')
                || $path === '/remote.php/webdav' || str_starts_with($path, '/remote.php/webdav/')
                || $path === '/remote.php/dav' || str_starts_with($path, '/remote.php/dav/')) {
                $this->handleWebDav($request);
                return;
            }

            $response->statusCode(404);
            $template = __DIR__ . '/../../templates/errors/404.php';
            if (file_exists($template)) {
                $error = 'Page not found.';
                ob_start();
                include $template;
                $response->html(ob_get_clean(), 404);
            } else {
                $response->html('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>', 404);
            }
            $response->send();
            return;
        }

        $middlewareChain = $this->buildMiddlewareChain(
            $route['middleware'],
            function (Request $req) use ($route, $response) {
                return $this->dispatch($route, $req, $response);
            }
        );

        try {
            $result = $middlewareChain($request);
            if ($result instanceof Response) {
                $result->send();
            }
        } catch (\Throwable $e) {
            $this->container->get(ErrorHandler::class)->handleException($e);
        }
    }

    private function dispatch(array $route, Request $request, Response $response): Response
    {
        [$class, $method] = $route['handler'];

        if ($this->container->has($class)) {
            $controller = $this->container->get($class);
        } else {
            $controller = new $class();
        }

        return $controller->$method($request, $response, $route['params'] ?? []);
    }

    private function buildMiddlewareChain(array $middleware, callable $core): callable
    {
        $chain = $core;
        foreach (array_reverse($middleware) as $mw) {
            $next = $chain;
            $chain = function (Request $request) use ($mw, $next) {
                if (is_array($mw)) {
                    [$class, $method] = $mw;
                    if ($this->container->has($class)) {
                        $instance = $this->container->get($class);
                    } else {
                        $instance = new $class();
                    }
                    return $instance->$method($request, $next);
                }
                return $mw($request, $next);
            };
        }
        return $chain;
    }

    private function setupDatabase(): void
    {
        $this->container->set(Connection::class, function (Container $c) {
            return Connection::create($c->get(Config::class));
        });
    }

    private function setupI18n(): void
    {
        $i18n = I18n::getInstance();
        $locale = 'en';
        $hasUserPref = false;

        $userId = $_SESSION['user_id'] ?? null;
        if ($userId !== null) {
            try {
                $db = $this->container->get(Connection::class);
                $row = $db->fetch('SELECT language FROM users WHERE id = ?', [$userId]);
                if ($row !== null && $row['language'] !== '') {
                    $locale = $row['language'];
                    $hasUserPref = true;
                }
            } catch (\Throwable $e) { error_log('Failed to load user language preference: ' . $e->getMessage()); }
        }

        if (!$hasUserPref) {
            $sessionLang = $_SESSION['language'] ?? '';
            $supported = $i18n->getAvailableLocales();
            if ($sessionLang !== '' && isset($supported[$sessionLang])) {
                $locale = $sessionLang;
                $hasUserPref = true;
            } else {
                $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
                if ($acceptLang !== '') {
                    $primary = strtolower(substr($acceptLang, 0, 2));
                    if (isset($supported[$primary])) {
                        $locale = $primary;
                    }
                }
            }
        }

        $i18n->setLocale($locale);
        $i18n->setHasUserPreference($hasUserPref);
        $this->container->set(I18n::class, $i18n);
    }

    private function setupSession(): void
    {
        $sessionConfig = $this->config->get('session', []);

        if (session_status() === PHP_SESSION_NONE) {
            session_name($sessionConfig['name'] ?? 'fileroll_session');

            $lifetime = (int) ($sessionConfig['lifetime'] ?? 7200);
            try {
                $db = $this->container->get(Connection::class);
                $row = $db->fetch('SELECT setting_value FROM settings WHERE setting_key = ?', ['session_lifetime']);
                if ($row && is_numeric($row['setting_value'])) {
                    $lifetime = (int) $row['setting_value'];
                }
            } catch (\Throwable $e) {
                // Keep config default if database is unavailable.
            }
            $this->config->set('session.lifetime', $lifetime);

            $cookieParams = [
                'lifetime' => $lifetime,
                'path' => defined('BASE_URL') && BASE_URL !== '' ? BASE_URL . '/' : '/',
                'httponly' => $sessionConfig['cookie_params']['httponly'] ?? true,
                'samesite' => $sessionConfig['cookie_params']['samesite'] ?? 'Strict',
                'secure' => $sessionConfig['cookie_params']['secure'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
            ];

            session_set_cookie_params($cookieParams);
            session_start();
        }
    }

    private function registerServices(): void
    {
        $this->container->set('auth.session', function (Container $c) {
            return new \FileRoll\Auth\Session($c->get(Connection::class), $c->get(Config::class));
        });

        $this->container->set('auth.csrf', function () {
            return new \FileRoll\Auth\CSRF();
        });

        $this->container->set('auth.password', function () {
            return new \FileRoll\Auth\Password();
        });

        $this->container->set(\FileRoll\File\FileRepository::class, function (Container $c) {
            return new \FileRoll\File\FileRepository($c->get(Connection::class));
        });

        $this->container->set(\FileRoll\User\UserRepository::class, function (Container $c) {
            return new \FileRoll\User\UserRepository($c->get(Connection::class));
        });

        $this->container->set(\FileRoll\Version\VersionRepository::class, function (Container $c) {
            return new \FileRoll\Version\VersionRepository($c->get(Connection::class));
        });

        $this->container->set(\FileRoll\Share\ShareRepository::class, function (Container $c) {
            return new \FileRoll\Share\ShareRepository($c->get(Connection::class));
        });

        $this->container->set(\FileRoll\Settings\SettingsRepository::class, function (Container $c) {
            return new \FileRoll\Settings\SettingsRepository($c->get(Connection::class));
        });

        $this->container->set(\FileRoll\File\Storage::class, function (Container $c) {
            return new \FileRoll\File\Storage($c->get(Config::class));
        });

        $this->container->set(\FileRoll\File\FileService::class, function (Container $c) {
            return new \FileRoll\File\FileService(
                $c->get(\FileRoll\File\FileRepository::class),
                $c->get(\FileRoll\Version\VersionRepository::class),
                $c->get(\FileRoll\File\Storage::class),
                $c->get(Connection::class),
                $c->get(Config::class)
            );
        });
    }

    private function registerRoutes(): void
    {
        $routeFile = __DIR__ . '/../../config/routes.php';
        if (file_exists($routeFile)) {
            $routes = require $routeFile;
            foreach ($routes as $definition => $handler) {
                $parts = explode(' ', $definition, 2);
                if (count($parts) === 2) {
                    $method = $parts[0];
                    $path = $parts[1];
                } else {
                    $method = 'GET';
                    $path = $parts[0];
                }

                $middleware = [];
                if (!in_array($path, ['/login', '/language', '/status.php', '/ocs/v2.php/cloud/capabilities', '/ocs/v2.php/cloud/user', '/graph/v1.0/me/drives', '/s/{token}', '/s/{token}/download'], true)) {
                    if (str_starts_with($path, '/admin/')) {
                        $middleware[] = [\FileRoll\Auth\Middleware::class, 'requireAuth'];
                        $middleware[] = [\FileRoll\Auth\Middleware::class, 'requireAdmin'];
                    } elseif ($path !== '/login' && $path !== '/logout') {
                        $middleware[] = [\FileRoll\Auth\Middleware::class, 'requireAuth'];
                    }
                }

                if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
                    $middleware[] = [\FileRoll\Auth\Middleware::class, 'verifyCsrf'];
                }

                $this->router->addRoute($method, $path, $handler, $middleware);
            }
        }
    }

    private function handleWebDav(Request $request): void
    {
        $server = new \FileRoll\WebDAV\Server();
        $server->handle($request);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
