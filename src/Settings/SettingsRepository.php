<?php

declare(strict_types=1);

namespace FileRoll\Settings;

use FileRoll\Database\Connection;

class SettingsRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->fetch('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
        return $row ? $row['setting_value'] : $default;
    }

    public function set(string $key, string $value): void
    {
        $existing = $this->db->fetch('SELECT setting_key FROM settings WHERE setting_key = ?', [$key]);
        if ($existing) {
            $this->db->update('settings', [
                'setting_value' => $value,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'setting_key = ?', [$key]);
        } else {
            $this->db->insert('settings', [
                'setting_key' => $key,
                'setting_value' => $value,
            ]);
        }
    }

    public function all(): array
    {
        $rows = $this->db->fetchAll('SELECT setting_key, setting_value FROM settings');
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }
}
