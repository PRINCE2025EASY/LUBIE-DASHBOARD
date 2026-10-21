<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Http\Response;
use Lubie\Repositories\CostRepository;
use Lubie\Repositories\ProjectSnapshotRepository;
use Lubie\Reports\CsvWriter;
use Lubie\Reports\PdfRenderer;
use Lubie\Security\AuthUser;
use Lubie\Security\Authorizer;
use Lubie\Support\Clock;
use Lubie\Support\Money;
use Lubie\Views\View;

/**
 * Report export (FR-26): same queries, KPI service and permissions as the dashboards.
 * Files are streamed, never stored on the server. Every export is audit-logged.
 */
final class ReportService
{
    /** report => roles allowed (in addition to the route permission reports.export) */
    public const TYPES = [
        'status' => ['director', 'pm', 'qs'],
        'delayed' => ['director', 'pm', 'qs'],
        'milestones' => ['director', 'pm', 'qs'],
        'expenditure' => ['director', 'pm', 'qs'],
        'budget' => ['director', 'pm', 'qs'],
        'cost-variance' => ['director', 'pm', 'qs'],
        'payments' => ['director', 'pm', 'qs'],
        'alerts' => ['director', 'pm', 'qs'],
        'client-summary' => ['director', 'pm', 'qs', 'client'],
        'audit' => ['admin'],
    ];

    public function __construct(
        private Database $db,
        private ProjectSnapshotRepository $snapshots,
        private CostRepository $cost,
        private KpiService $kpi,
        private DashboardService $dash,
        private Authorizer $authz,
        private AuditLogger $audit,
        private Clock $clock,
        private View $view,
    ) {
    }

    public function export(AuthUser $u, string $type, ?int $projectId, string $format, array $filters = []): Response
    {
        if (!isset(self::TYPES[$type])) {
            throw HttpException::notFound('Unknown report.');
        }
        if (!in_array($u->role, self::TYPES[$type], true)) {
            $this->audit->log($u->id, 'forbidden', 'report', $type);
            throw HttpException::forbidden('You cannot export this report.');
        }
        if (!in_array($format, ['pdf', 'csv', 'html'], true)) {
            throw HttpException::validation(['format' => 'Choose PDF, CSV or print view.']);
        }
        if ($type !== 'audit') {
            if ($projectId === null) {
                throw HttpException::validation(['project_id' => 'Choose a project.']);
            }
            $this->authz->requireProject($u, $projectId);
        }
        [$title, $headers, $rows, $summary] = $this->build($u, $type, $projectId, $filters);
        $this->audit->log($u->id, 'export', 'report', $type, null, ['project_id' => $projectId, 'format' => $format, 'rows' => count($rows)]);
        $stamp = $this->clock->now()->format('Ymd_Hi');
        $base = "lubie_{$type}" . ($projectId ? "_p{$projectId}" : '') . "_{$stamp}";
        if ($format === 'csv') {
            return Response::stream(static function () use ($headers, $rows) {
                $out = fopen('php://output', 'wb');
                CsvWriter::write($out, $headers, $rows);
                fclose($out);
            }, 'text/csv; charset=utf-8', $base . '.csv');
        }
        $html = $this->view->render('reports/report', [
            'title' => $title, 'headers' => $headers, 'rows' => $rows, 'summary' => $summary,
            'generated' => $this->clock->now()->format('d/m/Y H:i'), 'by' => $u->fullName,
        ], null);
        if ($format === 'html' || !PdfRenderer::available()) {
            // Print view: report template carries its own <style>; still no scripts allowed.
            return Response::html($html)->withHeader('Content-Security-Policy',
                "default-src 'none'; style-src 'unsafe-inline'; img-src data:; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        }
        $pdf = (new PdfRenderer())->render($html);
        return Response::stream(static function () use ($pdf) {
            echo $pdf;
        }, 'application/pdf', $base . '.pdf');
    }

    /** @return array{0:string, 1:list<string>, 2:list<list<scalar|null>>, 3:array<string, string>} */
    private function build(AuthUser $u, string $type, ?int $projectId, array $filters): array
    {
        $today = $this->clock->today();
        if ($type === 'audit') {
            $rows = array_map(static fn ($r) => [self::dt((string) $r['created_at']), $r['username'] ?? $r['username_attempted'] ?? '', $r['action'], $r['entity_type'], $r['entity_id'], $r['ip_address']],
                $this->db->all('SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 5000'));
            return ['Audit trail (latest 5,000 entries)', ['Date and time', 'User', 'Action', 'Entity', 'ID', 'IP address'], $rows, []];
        }
        $s = $this->snapshots->load((int) $projectId);
        $k = $this->kpi->compute($s, $today);
        $p = $s->project;
        $head = ['Project' => "{$p['code']} {$p['name']}", 'Status date' => self::d($today), 'Data last updated' => self::dt((string) $p['last_change_at'])];
        switch ($type) {
            case 'status':
                $sum = $head + ['Completion' => self::pct($k->completionPct) . ($k->weighting === 'duration' ? ' (duration-weighted)' : ''),
                    'Planned to date' => self::pct($k->plannedPct), 'SPI' => self::num($k->spi), 'CPI' => self::num($k->cpi),
                    'Budget consumed' => self::pct($k->budgetConsumedPct), 'Delayed activities' => (string) count($k->delayedActivities())];
                $rows = array_map(static fn ($a) => [$a['code'], $a['name'], self::d($a['planned_start']), self::d($a['planned_finish']), $a['planned_pct'], $a['pct'], $a['status_label']], $k->activities);
                return ['Project status report', ['Code', 'Activity', 'Planned start', 'Planned finish', 'Planned %', 'Actual %', 'Status'], $rows, $sum];
            case 'delayed':
                $rows = array_map(static fn ($a) => [$a['code'], $a['name'], self::d($a['planned_finish']), $a['days_late'], $a['pct']], $k->delayedActivities());
                return ['Delayed activities', ['Code', 'Activity', 'Planned finish', 'Days late', 'Actual %'], $rows, $head];
            case 'milestones':
                $rows = array_map(static fn ($m) => [$m['name'], self::d($m['due_date']), $m['achieved_date'] ? self::d((string) $m['achieved_date']) : '', Presenter::milestone($m, $today)['state']], $s->milestones);
                return ['Milestone status', ['Milestone', 'Due', 'Achieved', 'State'], $rows, $head];
            case 'expenditure':
                $rows = array_map(static fn ($e) => [self::d((string) $e['expense_date']), $e['category_label'], $e['description'], $e['reference_no'], $e['supplier_name'], $e['activity_code'], (string) $e['amount'], $e['status']],
                    $this->cost->expenditures((int) $projectId, 10000, 0));
                return ['Expenditure listing', ['Date', 'Category', 'Description', 'Reference', 'Supplier', 'Activity', 'Amount (GH₵)', 'Status'], $rows, $head + ['Total actual cost' => Money::format($k->ac)]];
            case 'budget':
                $rows = array_map(static fn ($b) => ['v' . $b['version_no'], (string) $b['amount'], $b['status'], (int) ($b['is_current'] ?? 0) === 1 ? 'Yes' : '', $b['reason'], $b['requested_by_name'], $b['decided_by_name'], $b['decided_at'] ? self::dt((string) $b['decided_at']) : ''],
                    $this->cost->budgets((int) $projectId));
                return ['Budget status', ['Version', 'Amount (GH₵)', 'Status', 'Current', 'Reason', 'Requested by', 'Decided by', 'Decided'], $rows,
                    $head + ['Current budget' => Money::format($k->currentBudget), 'Actual cost' => Money::format($k->ac), 'Budget variance' => Money::format($k->budgetVariance), 'Consumed' => self::pct($k->budgetConsumedPct)]];
            case 'cost-variance':
                $rows = [['BAC', Money::toString($k->bac)], ['PV', Money::toString($k->pv)], ['EV', Money::toString($k->ev)], ['AC', Money::toString($k->ac)],
                    ['SV = EV - PV', Money::toString($k->sv)], ['CV = EV - AC', Money::toString($k->cv)], ['SPI = EV / PV', self::num($k->spi)], ['CPI = EV / AC', self::num($k->cpi)]];
                return ['Cost variance summary', ['Indicator', 'Value (GH₵ or ratio)'], $rows, $head + ['Weighting' => $k->weighting]];
            case 'payments':
                $rows = array_map(static function ($c) use ($today, $p) {
                    $d = Presenter::certificate($c, $today, (int) $p['payment_period_days']);
                    return [$d['cert_no'], self::d($d['submitted_date']), $d['valuation_amount'], $d['amount_certified'], $d['paid'], $d['outstanding'], $d['status'] . ($d['overdue'] ? ' (overdue)' : '')];
                }, $this->cost->certificates((int) $projectId));
                return ['Valuations and payments', ['Certificate', 'Submitted', 'Valuation', 'Certified', 'Paid', 'Outstanding', 'Status'], $rows, $head];
            case 'alerts':
                $rows = array_map(static fn ($n) => [self::dt((string) $n['created_at']), $n['rule_code'], $n['severity'], $n['title'], $n['cleared_at'] ? self::dt((string) $n['cleared_at']) : 'open'],
                    $this->db->all('SELECT * FROM notifications WHERE project_id = ? ORDER BY created_at DESC LIMIT 2000', [$projectId]));
                return ['Alerts', ['Raised', 'Rule', 'Severity', 'Alert', 'Cleared'], $rows, $head];
            case 'client-summary':
                $c = $this->dash->clientSummary($u, (int) $projectId);
                $sum = ['Project' => "{$c['project']['code']} {$c['project']['name']}", 'Overall progress' => self::pct($c['completion_pct']),
                    'Contract sum' => Money::format(Money::toPesewas($c['payments']['contract_sum'])), 'Certified' => Money::format(Money::toPesewas($c['payments']['certified'])),
                    'Paid' => Money::format(Money::toPesewas($c['payments']['paid'])), 'Outstanding' => Money::format(Money::toPesewas($c['payments']['outstanding'])),
                    'Data last updated' => self::dt((string) $c['last_updated'])];
                $rows = array_map(static fn ($m) => [$m['name'], self::d($m['due_date']), $m['achieved_date'] ? self::d((string) $m['achieved_date']) : '', $m['state']], $c['milestones']);
                return ['Client progress and payment summary', ['Milestone', 'Due', 'Achieved', 'State'], $rows, $sum];
        }
        throw HttpException::notFound();
    }

    private static function d(string $iso): string
    {
        return $iso === '' ? '' : date('d/m/Y', (int) strtotime($iso));
    }

    private static function dt(string $iso): string
    {
        return $iso === '' ? '' : date('d/m/Y H:i', (int) strtotime($iso));
    }

    private static function pct(?float $v): string
    {
        return $v === null ? 'Not available' : number_format($v, 1) . '%';
    }

    private static function num(?float $v): string
    {
        return $v === null ? 'Not available' : number_format($v, 2);
    }
}
