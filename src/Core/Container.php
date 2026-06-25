<?php

declare(strict_types=1);

namespace FileRoll\Core;

class Container
{
    private static ?self $instance = null;

    private array $services = [];
    private array $resolved = [];
    private array $aliases = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Container not initialized');
        }
        return self::$instance;
    }

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public function set(string $id, callable|object $definition): void
    {
        $this->services[$id] = $definition;
        unset($this->resolved[$id]);
    }

    public function setAlias(string $alias, string $id): void
    {
        $this->aliases[$alias] = $id;
    }

    public function get(string $id): mixed
    {
        $id = $this->aliases[$id] ?? $id;

        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        if (!isset($this->services[$id])) {
            throw new \RuntimeException("Service '{$id}' not found in container");
        }

        $this->resolved[$id] = is_callable($this->services[$id])
            ? ($this->services[$id])($this)
            : $this->services[$id];

        return $this->resolved[$id];
    }

    public function has(string $id): bool
    {
        $id = $this->aliases[$id] ?? $id;
        return isset($this->services[$id]) || isset($this->resolved[$id]);
    }

    public function factory(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
        unset($this->resolved[$id]);
    }
}
