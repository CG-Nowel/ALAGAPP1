<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ClinicSetting extends Model
{
    protected string $table = 'clinic_settings';

    /**
     * Get a setting value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->findBy('setting_key', $key);
        if (!$row) {
            return $default;
        }

        return match ($row['setting_type']) {
            'INTEGER' => (int) $row['setting_value'],
            'BOOLEAN' => filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN),
            'JSON' => json_decode($row['setting_value'], true),
            default => $row['setting_value'],
        };
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value, string $type = 'STRING', ?int $updatedBy = null): void
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
            $type = 'JSON';
        }

        $existing = $this->findBy('setting_key', $key);
        if ($existing) {
            $this->updateById($existing['id'], [
                'setting_value' => (string) $value,
                'setting_type' => $type,
                'updated_by' => $updatedBy,
            ]);
        } else {
            $this->create([
                'setting_key' => $key,
                'setting_value' => (string) $value,
                'setting_type' => $type,
                'updated_by' => $updatedBy,
            ]);
        }
    }

    /**
     * Get all settings as key-value pairs.
     */
    public function getAllSettings(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM clinic_settings ORDER BY setting_key");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = match ($row['setting_type']) {
                'INTEGER' => (int) $row['setting_value'],
                'BOOLEAN' => filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN),
                'JSON' => json_decode($row['setting_value'], true),
                default => $row['setting_value'],
            };
        }
        return $settings;
    }
}
