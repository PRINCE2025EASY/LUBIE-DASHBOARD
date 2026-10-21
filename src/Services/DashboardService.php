<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Repositories\ProjectRepository;
use Lubie\Repositories\ProjectSnapshotRepository;
use Lubie\Security\AuthUser;
use Lubie\Security\Authorizer;
use Lubie\Support\Clock;
use Lubie\Support\Money;

/**
 * Dashboards (FR-23, FR-24, FR-27, FR-35). Same KpiService as reports and alerts.
 * Triggers the once-a-day alert evaluation (no cron on free hosts).
 */
final class DashboardService
{
    public function __construct(
        private Database $db,
        private ProjectRepository $projects,
        private ProjectSnapshotRepository $snapshots,
        private KpiService $kpi,
        private AlertService $alerts,
        private Authorizer $authz,
        private Clock $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function project(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId);
        $this->alerts->evaluateIfDue($projectId);
        $s = $this->snapshots->load($projectId);
        $today = $this->clock->today();
        $k = $this->kpi->compute($s, $today);
        $money = Presenter::seesMoney($u->role);
        $series = array_map(static function (array $pt) use ($money) {
            return $money
                ? ['date' => $pt['date'], 'pv' => Money::toString($pt['pv']), 'ev' => Money::toString($pt['ev']), 'pv_pct' => $pt['pv_pct'], 'ev_pct' => $pt['ev_pct']]
                : ['date' => $pt['date'], 'pv_pct' => $pt['pv_pct'], 'ev_pct' => $pt['ev_pct']];
        }, $this->kpi->series($s, $today));
        $out = [
            'project' => Presenter::project($s->project, $u->role),
            'kpis' => Presenter::kpis($k, $u->role),
            'series' => $series,
            'activities' => array_map(static fn (array $a) => Presenter::activity(['status' => 'active', 'budgeted_cost' => Money::toString($a['cost'])] + $a, $u->role, $a), $k->activities),
            'delayed' => array_map(static fn (array $a) => ['id' => $a['id'], 'code' => $a['code'], 'name' => $a['name'], 'days_late' => $a['days_late'], 'pct' => $a['pct'], 'planned_finish' => $a['planned_finish']], $k->delayedActivities()),
            'milestones' => array_map(static fn (array $m) => Presenter::milestone($m, $today), $s->milestones),
            'alerts' => $this->openAlerts($u, $projectId),
            'pending_approvals' => count($s->submittedProgress),
            'last_updated' => $s->project['last_change_at'],
            'change_seq' => (int) $s->project['change_seq'],
            'checked_at' => $this->clock->nowSql(),
        ];
        if ($money) {
            $byCat = [];
            foreach ($s->expenditures as $e) {
                $byCat[$e['category']] = ($byCat[$e['category']] ?? 0) + $e['amount'];
            }
            $out['expenditure_by_category'] = array_map(static fn ($v) => Money::toString($v), $byCat);
            $out['certificates'] = [
                'count' => count($s->certificates),
                'certified' => Money::toString(array_sum(array_map(static fn ($c) => $c['certified'] ?? 0, $s->certificates))),
                'paid' => Money::toString(array_sum(array_column($s->certificates, 'paid'))),
            ];
        }
        return $out;
    }

    /** @return list<array<string, mixed>> Director cross-project comparison (FR-35) */
    public function portfolio(AuthUser $u): array
    {
        $today = $this->clock->today();
        $out = [];
        foreach ($this->projects->listAll() as $p) {
            $pid = (int) $p['id'];
            $this->alerts->evaluateIfDue($pid);
            $s = $this->snapshots->load($pid);
            $k = $this->kpi->compute($s, $today);
            $next = null;
            foreach ($s->milestones as $m) {
                if ($m['achieved_date'] === null && ($next === null || $m['due_date'] < $next['due_date'])) {
                    $next = $m;
                }
            }
            $crit = (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE project_id = ? AND cleared_at IS NULL AND severity IN ('critical','high')", [$pid]);
            $tone = $crit > 0 || count($k->delayedActivities()) > 0 ? 'critical' : (($k->spi !== null && $k->spi < 0.9) || ($k->cpi !== null && $k->cpi < 0.9) ? 'warning' : 'ok');
            $out[] = Presenter::project($p, $u->role) + [
                'completion_pct' => $k->completionPct, 'planned_pct' => $k->plannedPct, 'weighting' => $k->weighting,
                'sv' => Money::toString($k->sv), 'cv' => Money::toString($k->cv), 'spi' => $k->spi, 'cpi' => $k->cpi,
                'budget_consumed_pct' => $k->budgetConsumedPct, 'delayed_count' => count($k->delayedActivities()),
                'next_milestone' => $next ? ['name' => $next['name'], 'due_date' => $next['due_date']] : null,
                'open_high_alerts' => $crit, 'tone' => $tone, 'data_currency_days' => $k->dataCurrencyDays,
            ];
        }
        return $out;
    }

    /** @return array<string, mixed> Administrator summary: counts only, no money (s6) */
    public function adminSummary(): array
    {
        return [
            'projects_by_status' => $this->db->all('SELECT status, COUNT(*) AS n FROM projects GROUP BY status'),
            'users_by_role' => $this->db->all('SELECT r.name AS role, u.status, COUNT(*) AS n FROM users u JOIN roles r ON r.id = u.role_id GROUP BY r.name, u.status ORDER BY r.name'),
            'open_alerts_by_severity' => $this->db->all('SELECT severity, COUNT(*) AS n FROM notifications WHERE cleared_at IS NULL GROUP BY severity'),
            'last_backup' => $this->db->one("SELECT kind, status, started_at, finished_at, byte_size FROM backup_log WHERE kind IN ('manual','scheduled') ORDER BY id DESC LIMIT 1"),
            'locked_users' => (int) $this->db->value("SELECT COUNT(*) FROM users WHERE status = 'locked'"),
            // Set-up checks shown to the Administrator only (never on the public health endpoint).
            'checks' => [
                ['PHP version 8.2 or newer', PHP_VERSION_ID >= 80200, PHP_VERSION],
                ['openssl extension (backups)', extension_loaded('openssl'), extension_loaded('openssl') ? 'on' : 'off: enable in php.ini'],
                ['PDF export library (Dompdf)', \Lubie\Reports\PdfRenderer::available(), \Lubie\Reports\PdfRenderer::available() ? 'installed' : 'not installed: print view used'],
                ['APP_KEY set (offline queue)', strlen((string) \Lubie\Support\Env::get('APP_KEY', '')) >= 32, ''],
                ['BACKUP_ENCRYPTION_KEY set', strlen((string) \Lubie\Support\Env::get('BACKUP_ENCRYPTION_KEY', '')) >= 32, ''],
                ['HTTPS session cookie', \Lubie\Support\Env::bool('SESSION_SECURE_COOKIE', false), \Lubie\Support\Env::bool('SESSION_SECURE_COOKIE', false) ? 'on' : 'off (fine on localhost, required online)'],
                ['SMS provider', \Lubie\Support\Env::get('SMS_PROVIDER', 'sandbox') !== 'sandbox', (string) \Lubie\Support\Env::get('SMS_PROVIDER', 'sandbox')],
            ],
        ];
    }

    /** @return array<string, mixed> one-screen client summary (FR-27); allow-list only */
    public function clientSummary(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId);
        $this->alerts->evaluateIfDue($projectId);
        $s = $this->snapshots->load($projectId);
        $today = $this->clock->today();
        $k = $this->kpi->compute($s, $today);
        $certified = array_sum(array_map(static fn ($c) => $c['certified'] ?? 0, $s->certificates));
        $paid = array_sum(array_column($s->certificates, 'paid'));
        $notices = $this->db->all(
            "SELECT n.title, n.description, n.created_at FROM notifications n JOIN notification_recipients r ON r.notification_id = n.id
              WHERE n.project_id = ? AND r.user_id = ? AND n.is_internal = 0 AND n.cleared_at IS NULL ORDER BY n.created_at DESC", [$projectId, $u->id]);
        return [
            'project' => ['id' => $s->id(), 'code' => $s->code(), 'name' => $s->project['name'], 'location' => $s->project['location'],
                'status' => $s->project['status'], 'planned_start' => $s->project['planned_start'], 'planned_finish' => $s->project['planned_finish'],
                'actual_start' => $s->project['actual_start'], 'actual_finish' => $s->project['actual_finish']],
            'completion_pct' => $k->completionPct,
            'planned_pct' => $k->plannedPct,
            'milestones' => array_map(static fn (array $m) => Presenter::milestone($m, $today), $s->milestones),
            'payments' => [
                'contract_sum' => (string) $s->project['contract_sum'],
                'certified' => Money::toString($certified),
                'paid' => Money::toString($paid),
                'outstanding' => Money::toString($certified - $paid),
                'certificates' => array_map(static fn ($c) => ['cert_no' => $c['cert_no'], 'submitted_date' => $c['submitted_date'], 'status' => $c['status']], $s->certificates),
            ],
            'notices' => $notices,
            'last_updated' => $s->project['last_change_at'],
            'change_seq' => (int) $s->project['change_seq'],
        ];
    }

    /** Light polling endpoint (< 1 KB). @return array<string, mixed> */
    public function changes(AuthUser $u, ?int $projectId, int $since): array
    {
        if ($projectId !== null) {
            $this->authz->requireProject($u, $projectId);
            $this->alerts->evaluateIfDue($projectId);
            $st = $this->projects->changeStamp($projectId) ?? throw HttpException::notFound();
            $seq = $st['seq'];
            $at = $st['at'];
        } else {
            $ids = $u->is('director', 'admin') ? null : array_map('intval', array_column($this->projects->listAssigned($u->id), 'id'));
            if ($ids === []) {
                $seq = 0;
                $at = null;
            } else {
                $where = $ids === null ? '' : ' WHERE id IN (' . implode(',', $ids) . ')';
                $r = $this->db->one('SELECT COALESCE(SUM(change_seq), 0) AS s, MAX(last_change_at) AS at FROM projects' . $where);
                $seq = (int) $r['s'];
                $at = $r['at'];
            }
        }
        $unread = (int) $this->db->value('SELECT COUNT(*) FROM notification_recipients r JOIN notifications n ON n.id = r.notification_id
                                          WHERE r.user_id = ? AND r.read_at IS NULL AND n.cleared_at IS NULL', [$u->id]);
        return ['changed' => $seq !== $since, 'seq' => $seq, 'last_change_at' => $at, 'unread_alerts' => $unread, 'server_time' => $this->clock->nowSql()];
    }

    /** @return list<array<string, mixed>> */
    private function openAlerts(AuthUser $u, int $projectId): array
    {
        return array_map(static fn (array $n) => Presenter::alert($n), $this->db->all(
            'SELECT r.id AS recipient_id, r.read_at, r.acknowledged_at, r.sms_status, n.*, p.code AS project_code
               FROM notification_recipients r JOIN notifications n ON n.id = r.notification_id JOIN projects p ON p.id = n.project_id
              WHERE r.user_id = ? AND n.project_id = ? AND n.cleared_at IS NULL ORDER BY FIELD(n.severity, \'critical\',\'high\',\'warning\',\'medium\',\'information\'), n.created_at DESC',
            [$u->id, $projectId]));
    }
}
