<?php

declare(strict_types=1);

namespace Lubie\Repositories;

use Lubie\Alerts\Thresholds;
use Lubie\Db\Database;

/** System settings (NFR-10) and alert thresholds (FR-21). */
final class SettingsRepository
{
    /** @var array<string, string>|null */
    private ?array $cache = null;

    public function __construct(private Database $db)
    {
    }

    public function get(string $key, string $default = ''): string
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->db->all('SELECT setting_key, value FROM system_settings') as $r) {
                $this->cache[(string) $r['setting_key']] = (string) $r['value'];
            }
        }
        return $this->cache[$key] ?? $default;
    }

    public function int(string $key, int $default): int
    {
        $v = $this->get($key, '');
        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        return $this->get($key, '0') === '1';
    }

    /** @return list<array<string, mixed>> */
    public function allSettings(): array
    {
        return $this->db->all('SELECT setting_key, value, value_type, description, updated_at FROM system_settings ORDER BY setting_key');
    }

    public function update(string $key, string $value, int $userId): void
    {
        $this->db->run('UPDATE system_settings SET value = ?, updated_by = ? WHERE setting_key = ?', [$value, $userId, $key]);
        $this->cache = null;
    }

    public function thresholdsFor(int $projectId): Thresholds
    {
        $vals = [];
        foreach ($this->db->all('SELECT setting_key, value FROM alert_settings WHERE project_id IS NULL') as $r) {
            $vals[(string) $r['setting_key']] = (float) $r['value'];
        }
        foreach ($this->db->all('SELECT setting_key, value FROM alert_settings WHERE project_id = ?', [$projectId]) as $r) {
            $vals[(string) $r['setting_key']] = (float) $r['value'];
        }
        return new Thresholds($vals);
    }
}
