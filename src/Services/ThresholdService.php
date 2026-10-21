<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Security\AuthUser;
use Lubie\Security\Authorizer;
use Lubie\Support\Clock;

/**
 * Two-level thresholds (FR-21): Director sets global values and limits, PM sets project
 * values inside the limits, QS proposes values for Director approval.
 */
final class ThresholdService
{
    public const KEYS = ['budget_warning_pct', 'budget_overrun_sms_pct', 'cpi_min', 'spi_min', 'milestone_due_days', 'stale_data_days', 'delay_sms_days'];

    public function __construct(private Database $db, private Authorizer $authz, private AuditLogger $audit, private AlertService $alerts, private Clock $clock)
    {
    }

    /** @return list<array<string, mixed>> */
    public function effective(AuthUser $u, ?int $projectId): array
    {
        if ($projectId !== null) {
            $this->authz->requireProject($u, $projectId);
        }
        $global = [];
        foreach ($this->db->all('SELECT setting_key, value, min_allowed, max_allowed, set_at FROM alert_settings WHERE project_id IS NULL') as $r) {
            $global[(string) $r['setting_key']] = $r;
        }
        $proj = [];
        if ($projectId !== null) {
            foreach ($this->db->all('SELECT setting_key, value, set_at FROM alert_settings WHERE project_id = ?', [$projectId]) as $r) {
                $proj[(string) $r['setting_key']] = $r;
            }
        }
        $out = [];
        foreach (self::KEYS as $k) {
            $g = $global[$k] ?? null;
            $out[] = ['key' => $k, 'global' => $g['value'] ?? null, 'min_allowed' => $g['min_allowed'] ?? null, 'max_allowed' => $g['max_allowed'] ?? null,
                'project' => $proj[$k]['value'] ?? null, 'effective' => $proj[$k]['value'] ?? ($g['value'] ?? null)];
        }
        return $out;
    }

    public function setGlobal(AuthUser $u, string $key, string $value, ?string $min, ?string $max): void
    {
        $this->assertKey($key);
        $v = $this->num($value, 'value');
        $mn = $min === null || $min === '' ? null : $this->num($min, 'min_allowed');
        $mx = $max === null || $max === '' ? null : $this->num($max, 'max_allowed');
        if (($mn !== null && $v < $mn) || ($mx !== null && $v > $mx) || ($mn !== null && $mx !== null && $mn > $mx)) {
            throw HttpException::validation(['value' => 'The value must lie between the minimum and maximum.']);
        }
        $old = $this->db->one('SELECT value, min_allowed, max_allowed FROM alert_settings WHERE project_id IS NULL AND setting_key = ?', [$key]);
        $this->db->run('UPDATE alert_settings SET value = ?, min_allowed = ?, max_allowed = ?, set_by = ?, set_at = ? WHERE project_id IS NULL AND setting_key = ?',
            [$v, $mn, $mx, $u->id, $this->clock->nowSql(), $key]);
        $this->audit->log($u->id, 'threshold_change', 'alert_setting', 'global:' . $key, $old, ['value' => $v, 'min' => $mn, 'max' => $mx]);
    }

    public function setProject(AuthUser $u, int $projectId, string $key, ?string $value): void
    {
        $this->authz->requireProject($u, $projectId);
        $this->assertKey($key);
        $old = $this->db->value('SELECT value FROM alert_settings WHERE project_id = ? AND setting_key = ?', [$projectId, $key]);
        if ($value === null || $value === '') {
            $this->db->run('DELETE FROM alert_settings WHERE project_id = ? AND setting_key = ?', [$projectId, $key]);
        } else {
            $v = $this->num($value, 'value');
            $this->assertWithinGlobal($key, $v);
            $this->db->run('INSERT INTO alert_settings (project_id, setting_key, value, set_by, set_at) VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE value = VALUES(value), set_by = VALUES(set_by), set_at = VALUES(set_at)',
                [$projectId, $key, $v, $u->id, $this->clock->nowSql()]);
        }
        $this->audit->log($u->id, 'threshold_change', 'alert_setting', $projectId . ':' . $key, ['value' => $old], ['value' => $value]);
        $this->alerts->evaluate($projectId);
    }

    public function propose(AuthUser $u, ?int $projectId, string $key, string $value, ?string $why): int
    {
        if ($projectId !== null) {
            $this->authz->requireProject($u, $projectId);
        }
        $this->assertKey($key);
        $v = $this->num($value, 'value');
        $id = $this->db->insert('INSERT INTO threshold_proposals (project_id, setting_key, proposed_value, justification, proposed_by, proposed_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$projectId, $key, $v, $why !== null ? mb_substr($why, 0, 300) : null, $u->id, $this->clock->nowSql()]);
        $this->audit->log($u->id, 'propose', 'threshold_proposal', (string) $id, null, ['key' => $key, 'value' => $v, 'project_id' => $projectId]);
        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function proposals(string $status = 'pending'): array
    {
        return $this->db->all('SELECT t.*, p.code AS project_code, u.full_name AS proposed_by_name FROM threshold_proposals t
                                 LEFT JOIN projects p ON p.id = t.project_id JOIN users u ON u.id = t.proposed_by WHERE t.status = ? ORDER BY t.proposed_at', [$status]);
    }

    public function decide(AuthUser $u, int $id, bool $approve): void
    {
        $t = $this->db->one("SELECT * FROM threshold_proposals WHERE id = ? AND status = 'pending'", [$id]) ?? throw HttpException::notFound('Proposal not found.');
        $this->db->transaction(function () use ($u, $id, $approve, $t) {
            $this->db->run('UPDATE threshold_proposals SET status = ?, decided_by = ?, decided_at = ? WHERE id = ?', [$approve ? 'approved' : 'rejected', $u->id, $this->clock->nowSql(), $id]);
            if ($approve) {
                if ($t['project_id'] === null) {
                    $this->db->run('UPDATE alert_settings SET value = ?, set_by = ?, set_at = ? WHERE project_id IS NULL AND setting_key = ?', [$t['proposed_value'], $u->id, $this->clock->nowSql(), $t['setting_key']]);
                } else {
                    $this->assertWithinGlobal((string) $t['setting_key'], (float) $t['proposed_value']);
                    $this->db->run('INSERT INTO alert_settings (project_id, setting_key, value, set_by, set_at) VALUES (?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE value = VALUES(value), set_by = VALUES(set_by), set_at = VALUES(set_at)',
                        [$t['project_id'], $t['setting_key'], $t['proposed_value'], $u->id, $this->clock->nowSql()]);
                }
            }
            $this->audit->log($u->id, $approve ? 'approve' : 'reject', 'threshold_proposal', (string) $id, null, ['key' => $t['setting_key'], 'value' => $t['proposed_value']]);
        });
        if ($approve && $t['project_id'] !== null) {
            $this->alerts->evaluate((int) $t['project_id']);
        }
    }

    private function assertWithinGlobal(string $key, float $v): void
    {
        $g = $this->db->one('SELECT min_allowed, max_allowed FROM alert_settings WHERE project_id IS NULL AND setting_key = ?', [$key]);
        if ($g !== null && (($g['min_allowed'] !== null && $v < (float) $g['min_allowed']) || ($g['max_allowed'] !== null && $v > (float) $g['max_allowed']))) {
            throw HttpException::validation(['value' => "Choose a value between {$g['min_allowed']} and {$g['max_allowed']} (Director's limits)."]);
        }
    }

    private function assertKey(string $key): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw HttpException::notFound('Unknown threshold.');
        }
    }

    private function num(string $v, string $field): float
    {
        if (!is_numeric($v) || (float) $v < 0 || (float) $v > 1000) {
            throw HttpException::validation([$field => 'Enter a number from 0 to 1000.']);
        }
        return round((float) $v, 2);
    }
}
