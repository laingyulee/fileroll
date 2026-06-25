<?php

declare(strict_types=1);

namespace FileRoll\Core;

class Config
{
    private array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new \RuntimeException("Config file must return an array: {$path}");
        }
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $ref = &$this->data;

        foreach ($keys as $k) {
            if (!is_array($ref)) {
                $ref = [];
            }
            $ref = &$ref[$k];
        }

        $ref = $value;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
