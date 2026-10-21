<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Domain\KpiResult;
use Lubie\Support\Money;

/**
 * DTOs with field-level filtering by role (Design Specification s6). This is the only
 * place that decides which fields leave the server. Site engineers never receive cost
 * fields; clients receive only their summary. Hidden fields are omitted, not blanked.
 */
final class Presenter
{
    private const MONEY_ROLES = ['director', 'pm', 'qs'];

    public static function seesMoney(string $role): bool
    {
        return in_array($role, self::MONEY_ROLES, true);
    }

    /** @param array<string, mixed> $p */
    public static function project(array $p, string $role): array
    {
        $out = [
            'id' => (int) $p['id'], 'code' => $p['code'], 'name' => $p['name'], 'project_type' => $p['project_type'],
            'location' => $p['location'], 'client_name' => $p['client_name'], 'status' => $p['status'],
            'planned_start' => $p['planned_start'], 'planned_finish' => $p['planned_finish'],
            'actual_start' => $p['actual_start'], 'actual_finish' => $p['actual_finish'], 'is_demo' => (bool) $p['is_demo'],
            'last_change_at' => $p['last_change_at'], 'change_seq' => (int) ($p['change_seq'] ?? 0),
        ];
        if (in_array($role, ['director', 'pm', 'qs', 'client', 'admin'], true)) {
            $out['contract_sum'] = (string) $p['contract_sum'];
        }
        if (in_array($role, ['director', 'pm', 'qs', 'admin'], true)) {
            $out['payment_period_days'] = (int) $p['payment_period_days'];
        }
        return $out;
    }

    /** @param array<string, mixed> $a */
    public static function activity(array $a, string $role, ?array $kpiRow = null): array
    {
        $out = [
            'id' => (int) $a['id'], 'code' => $a['code'], 'name' => $a['name'],
            'planned_start' => $a['planned_start'], 'planned_finish' => $a['planned_finish'],
            'actual_start' => $a['actual_start'], 'actual_finish' => $a['actual_finish'], 'status' => $a['status'],
        ];
        if (self::seesMoney($role)) {
            $out['budgeted_cost'] = $a['budgeted_cost'] !== null ? (string) $a['budgeted_cost'] : null;
        }
        if ($kpiRow !== null) {
            $out += ['pct_complete' => $kpiRow['pct'], 'planned_pct' => $kpiRow['planned_pct'], 'schedule_status' => $kpiRow['status'],
                'schedule_label' => $kpiRow['status_label'], 'tone' => $kpiRow['tone'], 'days_late' => $kpiRow['days_late']];
        }
        return $out;
    }

    /** @return array<string, mixed> */
    public static function kpis(KpiResult $k, string $role): array
    {
        $out = [
            'as_of' => $k->asOf, 'weighting' => $k->weighting,
            'completion_pct' => $k->completionPct, 'planned_pct' => $k->plannedPct, 'spi' => $k->spi,
            'data_currency_days' => $k->dataCurrencyDays, 'days_to_planned_finish' => $k->daysToPlannedFinish,
            'delayed_count' => count($k->delayedActivities()),
        ];
        if (self::seesMoney($role)) {
            $out += [
                'bac' => Money::toString($k->bac), 'pv' => Money::toString($k->pv), 'ev' => Money::toString($k->ev), 'ac' => Money::toString($k->ac),
                'sv' => Money::toString($k->sv), 'cv' => Money::toString($k->cv), 'cpi' => $k->cpi,
                'current_budget' => Money::toString($k->currentBudget), 'budget_variance' => Money::toString($k->budgetVariance),
                'budget_consumed_pct' => $k->budgetConsumedPct,
            ];
        }
        return $out;
    }

    /** @param array<string, mixed> $m */
    public static function milestone(array $m, string $today): array
    {
        $state = $m['achieved_date'] !== null ? 'achieved' : ((string) $m['due_date'] < $today ? 'overdue' : 'upcoming');
        return ['id' => (int) $m['id'], 'name' => $m['name'], 'due_date' => $m['due_date'], 'achieved_date' => $m['achieved_date'],
            'activity_id' => isset($m['activity_id']) && $m['activity_id'] !== null ? (int) $m['activity_id'] : null, 'state' => $state];
    }

    /** @param array<string, mixed> $c */
    public static function certificate(array $c, string $today, int $periodDays): array
    {
        $cert = $c['amount_certified'] !== null ? Money::toPesewas((string) $c['amount_certified']) : null;
        $paid = (int) Money::toPesewas((string) $c['paid']);
        $fully = $cert !== null && $paid >= $cert;
        $overdue = !$fully && KpiService::days((string) $c['submitted_date'], $today) > $periodDays;
        return [
            'id' => (int) $c['id'], 'cert_no' => $c['cert_no'], 'valuation_amount' => (string) $c['valuation_amount'],
            'amount_certified' => $c['amount_certified'] !== null ? (string) $c['amount_certified'] : null,
            'paid' => Money::toString($paid), 'outstanding' => $cert !== null ? Money::toString($cert - $paid) : null,
            'submitted_date' => $c['submitted_date'], 'certified_date' => $c['certified_date'], 'status' => $c['status'], 'overdue' => $overdue,
        ];
    }

    /** @param array<string, mixed> $n */
    public static function alert(array $n): array
    {
        return [
            'recipient_id' => (int) $n['recipient_id'], 'id' => (int) $n['id'], 'rule' => $n['rule_code'], 'severity' => $n['severity'],
            'project_id' => (int) $n['project_id'], 'project_code' => $n['project_code'], 'title' => $n['title'], 'description' => $n['description'],
            'status' => $n['cleared_at'] !== null ? 'resolved' : ($n['acknowledged_at'] !== null ? 'acknowledged' : ($n['read_at'] !== null ? 'read' : 'delivered')),
            'created_at' => $n['created_at'], 'cleared_at' => $n['cleared_at'], 'sms_status' => $n['sms_status'] ?? 'none',
        ];
    }
}
