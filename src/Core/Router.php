<?php

declare(strict_types=1);

namespace FileRoll\Core;

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, array $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, array $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function addRoute(string $method, string $path, array $handler, array $middleware = []): self
    {
        $pattern = $this->buildPattern($path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
        return $this;
    }

    public function middleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    public function match(string $method, string $uri): ?array
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === null) {
            return null;
        }
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'middleware' => array_merge($this->middleware, $route['middleware']),
                    'path' => $route['path'],
                ];
            }
        }

        return null;
    }

    public function loadRoutes(array $routes): self
    {
        foreach ($routes as $definition => $handler) {
            $parts = explode(' ', $definition, 2);
            if (count($parts) === 2) {
                $method = $parts[0];
                $path = $parts[1];
            } else {
                $method = 'GET';
                $path = $parts[0];
            }
            $this->addRoute($method, $path, $handler);
        }
        return $this;
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '/?$#';
    }
}
