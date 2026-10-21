<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Rules;
use Lubie\Db\Database;
use Lubie\Repositories\ProjectSnapshotRepository;
use Lubie\Repositories\SettingsRepository;
use Lubie\Sms\SmsDispatcher;
use Lubie\Support\Clock;
use PDOException;

/**
 * Alert engine (FR-20, FR-22). Evaluated after saves and on the first dashboard request of
 * the day (no cron). Creates an alert only when its condition first becomes true (unique
 * open dedup key), clears it when false, and sends at most one SMS per recipient.
 */
final class AlertService
{
    /** @var list<AlertRule> */
    private array $rules;

    public function __construct(
        private Database $db,
        private ProjectSnapshotRepository $snapshots,
        private KpiService $kpi,
        private SettingsRepository $settings,
        private SmsDispatcher $sms,
        private Clock $clock,
    ) {
        $this->rules = [
            new Rules\A01ActivityDelayed(), new Rules\A02LateStart(), new Rules\A03MilestoneDueSoon(), new Rules\A04MilestoneOverdue(),
            new Rules\A05BudgetWarning(), new Rules\A06BudgetExceeded(), new Rules\A06bCriticalOverrun(), new Rules\A07LowCpi(),
            new Rules\A08LowSpi(), new Rules\A09CertificateOverdue(), new Rules\A10MaterialLow(), new Rules\A11StaleData(),
            new Rules\A12BudgetRevisionPending(), new Rules\A13ProgressAwaitingApproval(), new Rules\A14EquipmentFaulty(), new Rules\A15StoppageReported(),
        ];
    }

    /** @return list<Condition> */
    public function conditions(int $projectId): array
    {
        $snap = $this->snapshots->load($projectId);
        if (!in_array($snap->project['status'], ['active', 'on_hold'], true)) {
            return [];
        }
        $today = $this->clock->today();
        $k = $this->kpi->compute($snap, $today);
        $t = $this->settings->thresholdsFor($projectId);
        $out = [];
        foreach ($this->rules as $rule) {
            foreach ($rule->evaluate($snap, $k, $t, $today) as $c) {
                $out[$c->dedupKey] = $c;
            }
        }
        return array_values($out);
    }

    /** @return array{created:int, cleared:int} */
    public function evaluate(int $projectId): array
    {
        $conds = [];
        foreach ($this->conditions($projectId) as $c) {
            $conds[$c->dedupKey] = $c;
        }
        $now = $this->clock->nowSql();
        $open = [];
        foreach ($this->db->all("SELECT id, dedup_key FROM notifications WHERE project_id = ? AND cleared_at IS NULL", [$projectId]) as $r) {
            $open[(string) $r['dedup_key']] = (int) $r['id'];
        }
        $created = $cleared = 0;
        foreach ($open as $key => $id) {
            if (!isset($conds[$key])) {
                $this->db->run("UPDATE notifications SET cleared_at = ?, status = 'resolved' WHERE id = ?", [$now, $id]);
                $cleared++;
            }
        }
        foreach ($conds as $key => $c) {
            if (isset($open[$key])) {
                continue;
            }
            try {
                $nid = $this->db->insert(
                    'INSERT INTO notifications (rule_code, severity, project_id, entity_type, entity_id, dedup_key, title, description, is_internal, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$c->rule, $c->severity, $projectId, $c->entityType, $c->entityId, $c->dedupKey, mb_substr($c->title, 0, 150), mb_substr($c->description, 0, 500), $c->clientVisible ? 0 : 1, $now]
                );
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    continue; // another request created it first (unique open key)
                }
                throw $e;
            }
            $created++;
            $this->addRecipients($nid, $projectId, $c);
        }
        $this->db->run('UPDATE projects SET last_alert_eval_on = ? WHERE id = ?', [$this->clock->today(), $projectId]);
        $this->sms->retryDue();
        return ['created' => $created, 'cleared' => $cleared];
    }

    /** Runs evaluate() once per project per day (first dashboard request). */
    public function evaluateIfDue(int $projectId): void
    {
        $last = $this->db->value('SELECT last_alert_eval_on FROM projects WHERE id = ?', [$projectId]);
        if ($last === null || (string) $last < $this->clock->today()) {
            $this->evaluate($projectId);
        }
    }

    private function addRecipients(int $notificationId, int $projectId, Condition $c): void
    {
        $roles = $c->recipients;
        if (!$c->clientVisible) {
            $roles = array_values(array_diff($roles, ['client']));
        }
        $users = $this->db->all(
            "SELECT DISTINCT u.id, u.phone_e164, u.sms_opt_in, r.code
               FROM users u JOIN roles r ON r.id = u.role_id
          LEFT JOIN project_assignments a ON a.user_id = u.id AND a.project_id = ?
              WHERE u.status = 'active' AND (a.id IS NOT NULL OR r.code = 'director')", [$projectId]);
        foreach ($users as $u) {
            if (!in_array($u['code'], $roles, true)) {
                continue;
            }
            $rid = $this->db->insert('INSERT INTO notification_recipients (notification_id, user_id) VALUES (?, ?)', [$notificationId, $u['id']]);
            if (in_array($u['code'], $c->smsTo, true) && $u['code'] !== 'client' && (int) $u['sms_opt_in'] === 1) {
                $status = $this->sms->dispatch($rid, $u['phone_e164'], 'Lubie ' . $c->title . '. ' . $c->description);
                $this->db->run('UPDATE notification_recipients SET sms_status = ? WHERE id = ?', [$status, $rid]);
            }
        }
    }
}
