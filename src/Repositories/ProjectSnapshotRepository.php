<?php

declare(strict_types=1);

namespace Lubie\Repositories;

use Lubie\Db\Database;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Http\HttpException;
use Lubie\Support\Money;

/** Loads one project's monitoring data in a fixed number of queries (no N+1). */
final class ProjectSnapshotRepository
{
    public function __construct(private Database $db)
    {
    }

    public function load(int $projectId): ProjectSnapshot
    {
        $p = $this->db->one('SELECT * FROM projects WHERE id = ?', [$projectId]);
        if ($p === null) {
            throw HttpException::notFound('Project not found.');
        }
        $hist = [];
        foreach ($this->db->all(
            "SELECT pr.activity_id, pr.record_date, pr.percent_complete
               FROM progress_records pr JOIN activities a ON a.id = pr.activity_id
              WHERE a.project_id = ? AND pr.status = 'approved'
              ORDER BY pr.record_date, pr.id", [$projectId]) as $r) {
            $hist[] = ['activity_id' => (int) $r['activity_id'], 'record_date' => (string) $r['record_date'], 'pct' => self::pct($r['percent_complete'])];
        }
        $latest = [];
        foreach ($hist as $h) {
            $latest[$h['activity_id']] = $h;
        }
        $acts = [];
        foreach ($this->db->all("SELECT * FROM activities WHERE project_id = ? AND status = 'active' ORDER BY planned_start, code", [$projectId]) as $a) {
            $id = (int) $a['id'];
            $acts[] = [
                'id' => $id, 'code' => (string) $a['code'], 'name' => (string) $a['name'],
                'planned_start' => (string) $a['planned_start'], 'planned_finish' => (string) $a['planned_finish'],
                'actual_start' => $a['actual_start'], 'actual_finish' => $a['actual_finish'],
                'cost' => Money::toPesewas($a['budgeted_cost'] === null ? null : (string) $a['budgeted_cost']),
                'pct' => isset($latest[$id]) ? $latest[$id]['pct'] : null,
                'pct_date' => isset($latest[$id]) ? $latest[$id]['record_date'] : null,
                'has_progress' => isset($latest[$id]),
            ];
        }
        $exp = array_map(static fn (array $e) => [
            'date' => (string) $e['expense_date'], 'amount' => (int) Money::toPesewas((string) $e['amount']), 'category' => (string) $e['code'],
        ], $this->db->all(
            "SELECT e.expense_date, e.amount, c.code FROM expenditures e JOIN expenditure_categories c ON c.id = e.category_id
              WHERE e.project_id = ? AND e.status = 'active'", [$projectId]));
        $budget = $this->db->value('SELECT amount FROM budgets WHERE project_id = ? AND is_current = 1', [$projectId]);
        $ms = array_map(static fn (array $m) => [
            'id' => (int) $m['id'], 'name' => (string) $m['name'], 'due_date' => (string) $m['due_date'],
            'achieved_date' => $m['achieved_date'], 'activity_id' => $m['activity_id'] !== null ? (int) $m['activity_id'] : null,
        ], $this->db->all('SELECT id, name, due_date, achieved_date, activity_id FROM milestones WHERE project_id = ? ORDER BY due_date', [$projectId]));
        $certs = array_map(static fn (array $c) => [
            'id' => (int) $c['id'], 'cert_no' => (string) $c['cert_no'], 'valuation' => (int) Money::toPesewas((string) $c['valuation_amount']),
            'certified' => Money::toPesewas($c['amount_certified'] === null ? null : (string) $c['amount_certified']),
            'paid' => (int) Money::toPesewas((string) $c['paid']), 'submitted_date' => (string) $c['submitted_date'],
            'certified_date' => $c['certified_date'], 'status' => (string) $c['status'],
        ], $this->db->all(
            'SELECT c.*, COALESCE((SELECT SUM(t.amount) FROM payment_transactions t WHERE t.certificate_id = c.id), 0) AS paid
               FROM payment_certificates c WHERE c.project_id = ? ORDER BY c.submitted_date, c.id', [$projectId]));
        $res = array_map(static fn (array $r) => [
            'id' => (int) $r['id'], 'name' => (string) $r['name'], 'category' => (string) $r['cat'], 'unit' => (string) $r['unit'],
            'balance' => (float) $r['balance'], 'reorder_level' => $r['reorder_level'] !== null ? (float) $r['reorder_level'] : null,
            'equipment_status' => $r['equipment_status'],
        ], $this->db->all(
            "SELECT r.id, r.name, r.unit, r.reorder_level, r.equipment_status, rc.code AS cat,
                    COALESCE(SUM(CASE u.movement WHEN 'received' THEN u.quantity WHEN 'used' THEN -u.quantity ELSE 0 END), 0) AS balance
               FROM resources r JOIN resource_categories rc ON rc.id = r.category_id
               LEFT JOIN resource_usage u ON u.resource_id = r.id
              WHERE r.project_id = ? AND r.status = 'active' GROUP BY r.id, r.name, r.unit, r.reorder_level, r.equipment_status, rc.code", [$projectId]));
        $pending = array_map(static fn (array $b) => ['id' => (int) $b['id'], 'version_no' => (int) $b['version_no'], 'amount' => (int) Money::toPesewas((string) $b['amount'])],
            $this->db->all("SELECT id, version_no, amount FROM budgets WHERE project_id = ? AND status = 'pending'", [$projectId]));
        $submitted = array_map(static fn (array $r) => ['id' => (int) $r['id'], 'activity_id' => (int) $r['activity_id'], 'record_date' => (string) $r['record_date']],
            $this->db->all("SELECT pr.id, pr.activity_id, pr.record_date FROM progress_records pr JOIN activities a ON a.id = pr.activity_id
                             WHERE a.project_id = ? AND pr.status = 'submitted'", [$projectId]));
        $critical = array_map(static fn (array $r) => ['id' => (int) $r['id'], 'activity_id' => (int) $r['activity_id'], 'reason' => (string) $r['label'], 'record_date' => (string) $r['record_date']],
            $this->db->all("SELECT pr.id, pr.activity_id, pr.record_date, d.label FROM progress_records pr
                              JOIN activities a ON a.id = pr.activity_id JOIN delay_reasons d ON d.id = pr.delay_reason_id
                             WHERE a.project_id = ? AND d.raises_critical = 1 AND pr.status <> 'returned'", [$projectId]));
        $lastApproved = $hist ? max(array_column($hist, 'record_date')) : null;

        return new ProjectSnapshot($p, $acts, $hist, $exp, $budget === null ? null : Money::toPesewas((string) $budget),
            $ms, $certs, $res, $pending, $submitted, $critical, $lastApproved);
    }

    public static function pct(mixed $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }
}
