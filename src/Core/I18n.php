<?php

declare(strict_types=1);

namespace FileRoll\Core;

class I18n
{
    private static ?self $instance = null;
    private array $translations = [];
    private array $fallback = [];
    private string $locale = 'en';
    private bool $hasUserPreference = false;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->translations = $this->load($locale);
        if ($locale !== 'en') {
            $this->fallback = $this->load('en');
        } else {
            $this->fallback = [];
        }
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setHasUserPreference(bool $value): void
    {
        $this->hasUserPreference = $value;
    }

    public function hasUserPreference(): bool
    {
        return $this->hasUserPreference;
    }

    public function t(string $key, array $params = []): string
    {
        $text = $this->translations[$key] ?? $this->fallback[$key] ?? $key;

        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $text = str_replace('{' . $k . '}', (string) $v, $text);
            }
        }

        return $text;
    }

    public function getAvailableLocales(): array
    {
        return [
            'en' => 'English',
            'zh' => '中文',
            'ja' => '日本語',
            'ko' => '한국어',
            'it' => 'Italiano',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
        ];
    }

    private function load(string $locale): array
    {
        $path = __DIR__ . '/../../lang/' . $locale . '.php';
        if (file_exists($path)) {
            $data = require $path;
            return is_array($data) ? $data : [];
        }
        return [];
    }
}
