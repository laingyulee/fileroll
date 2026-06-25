<?php

declare(strict_types=1);

namespace FileRoll\Core;

class ErrorHandler
{
    private bool $debug;
    private static bool $handling = false;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        if (in_array($errno, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            return false;
        }

        $this->logError($errno, $errstr, $errfile, $errline);
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public function handleException(\Throwable $e): void
    {
        $this->logException($e);
        $this->renderError($e);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $e = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            $this->logException($e);
            $this->renderError($e);
        }
    }

    private function renderError(\Throwable $e): void
    {
        if (self::$handling) {
            echo '<h1>Critical: Error during error rendering</h1>';
            exit(1);
        }
        self::$handling = true;

        http_response_code(500);

        $request = Request::fromGlobals();
        if ($request->isAjax() || $request->isJson()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $this->debug ? $e->getMessage() : 'Internal Server Error',
            ]);
            return;
        }

        if (!defined('BASE_URL')) {
            $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            $appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
            $baseUrl = str_replace($docRoot, '', $appRoot);
            define('BASE_URL', $baseUrl !== '' ? $baseUrl : '');
        }

        if ($this->debug) {
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Error</title>';
            echo '<style>body{font-family:monospace;padding:40px;background:#1a1a2e;color:#e94560}';
            echo 'h1{color:#e94560}pre{background:#16213e;padding:20px;border-radius:8px;overflow-x:auto;color:#eee}';
            echo '.file{color:#0f3460}.line{color:#533483}</style></head><body>';
            echo '<h1>' . htmlspecialchars(get_class($e)) . '</h1>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</pre>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</body></html>';
        } else {
            $template = __DIR__ . '/../../templates/errors/500.php';
            if (file_exists($template)) {
                include $template;
            } else {
                echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>500 Internal Server Error</title></head><body><h1>Internal Server Error</h1><p>An error occurred. Please try again later.</p></body></html>';
            }
        }
    }

    private function logError(int $errno, string $errstr, string $errfile, int $errline): void
    {
        $level = match ($errno) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR => 'CRITICAL',
            E_WARNING, E_CORE_WARNING => 'WARNING',
            E_NOTICE, E_STRICT => 'NOTICE',
            default => 'ERROR',
        };
        $message = date('Y-m-d H:i:s') . " [{$level}] {$errstr} in {$errfile}:{$errline}\n";
        error_log($message, 3, __DIR__ . '/../../storage/error.log');
    }

    private function logException(\Throwable $e): void
    {
        $message = date('Y-m-d H:i:s') . " [EXCEPTION] " . get_class($e) . ": {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n";
        $message .= $e->getTraceAsString() . "\n\n";
        error_log($message, 3, __DIR__ . '/../../storage/error.log');
    }
}
