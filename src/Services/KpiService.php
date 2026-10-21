<?php

declare(strict_types=1);

namespace Lubie\Services;

use DateTimeImmutable;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Domain\ScheduleStatus;
use Lubie\Support\Money;

/**
 * KPI engine (FR-10, FR-11, FR-12, FR-16, FR-17; Design Specification s9.1).
 * Pure calculation on a snapshot: no database access, fully unit-tested against the
 * thesis worked example (Table 2.1) and edge cases. Only APPROVED progress counts.
 */
final class KpiService
{
    public function compute(ProjectSnapshot $s, string $asOf): KpiResult
    {
        $acts = [];
        $priced = array_values(array_filter($s->activities, static fn (array $a) => $a['cost'] !== null && $a['cost'] > 0));
        $weighting = $priced ? 'cost' : ($s->activities ? 'duration' : 'none');

        $bac = $ev = $pv = 0;
        $wTotal = $wEarned = 0.0;
        $wPlanned = 0.0;
        foreach ($s->activities as $a) {
            $pct = $this->pctAt($s, $a['id'], $asOf, $a);
            $frac = self::plannedFraction($a['planned_start'], $a['planned_finish'], $asOf);
            $status = self::status($a, $pct, $asOf);
            if ($weighting === 'cost' && $a['cost'] !== null && $a['cost'] > 0) {
                $bac += $a['cost'];
                $ev += Money::mulDiv($a['cost'], $pct, 10000);
                $pv += (int) round($a['cost'] * $frac);
            } elseif ($weighting === 'duration') {
                $w = self::days($a['planned_start'], $a['planned_finish']) + 1;
                $wTotal += $w;
                $wEarned += $w * $pct / 10000;
                $wPlanned += $w * $frac;
            }
            $acts[] = [
                'id' => $a['id'], 'code' => $a['code'], 'name' => $a['name'],
                'planned_start' => $a['planned_start'], 'planned_finish' => $a['planned_finish'],
                'actual_start' => $a['actual_start'], 'actual_finish' => $a['actual_finish'],
                'cost' => $a['cost'], 'pct' => $pct / 100, 'planned_pct' => round($frac * 100, 2),
                'status' => $status->value, 'status_label' => $status->label(), 'tone' => $status->tone(),
                'days_late' => $status === ScheduleStatus::Delayed ? self::days($a['planned_finish'], $asOf) : 0,
                'priced' => $a['cost'] !== null && $a['cost'] > 0,
            ];
        }

        $ac = 0;
        foreach ($s->expenditures as $e) {
            if ($e['date'] <= $asOf) {
                $ac += $e['amount'];
            }
        }

        if ($weighting === 'cost') {
            $completion = $bac > 0 ? round($ev / $bac * 100, 2) : null;
            $planned = $bac > 0 ? round($pv / $bac * 100, 2) : null;
            $sv = $ev - $pv;
            $spi = $pv > 0 ? round($ev / $pv, 2) : null;
            $cv = $ev - $ac;
            $cpi = $ac > 0 ? round($ev / $ac, 2) : null;
        } else {
            $completion = $wTotal > 0 ? round($wEarned / $wTotal * 100, 2) : null;
            $planned = $wTotal > 0 ? round($wPlanned / $wTotal * 100, 2) : null;
            $spi = $wPlanned > 0 ? round($wEarned / $wPlanned, 2) : null;
            $bac = $ev = $pv = $sv = $cv = null;
            $cpi = null;
        }

        $budget = $s->currentBudget;
        $variance = $budget !== null ? $budget - $ac : null;
        $consumed = $budget !== null && $budget > 0 ? round($ac / $budget * 100, 2) : null;
        $last = $s->lastApprovedProgressDate;
        $currency = $last !== null ? max(0, self::days($last, $asOf)) : null;

        return new KpiResult(
            $asOf, $weighting, $bac, $pv, $ev, $ac, $completion, $planned, $sv, $spi, $cv, $cpi,
            $budget, $variance, $consumed, $currency,
            self::days($asOf, (string) $s->project['planned_finish']), $acts,
        );
    }

    /**
     * Cumulative planned and earned value by period (S-curve, FR-11). Weekly points,
     * monthly when the project is longer than 60 weeks.
     * @return list<array{date:string, pv:?int, ev:?int, pv_pct:?float, ev_pct:?float}>
     */
    public function series(ProjectSnapshot $s, string $asOf): array
    {
        $start = (string) $s->project['planned_start'];
        $end = max((string) $s->project['planned_finish'], $asOf);
        $weeks = intdiv(self::days($start, $end), 7) + 1;
        $step = $weeks > 60 ? 'P1M' : 'P7D';
        $out = [];
        $d = new DateTimeImmutable($start);
        $stop = new DateTimeImmutable($end);
        while ($d <= $stop) {
            $out[] = $this->point($s, $d->format('Y-m-d'), $asOf);
            $d = $d->add(new \DateInterval($step));
        }
        $out[] = $this->point($s, $end, $asOf);
        return $out;
    }

    /** @return array{date:string, pv:?int, ev:?int, pv_pct:?float, ev_pct:?float} */
    private function point(ProjectSnapshot $s, string $date, string $asOf): array
    {
        $k = $this->compute($s, $date);
        $future = $date > $asOf;
        return [
            'date' => $date,
            'pv' => $k->pv,
            'ev' => $future ? null : $k->ev,
            'pv_pct' => $k->plannedPct,
            'ev_pct' => $future ? null : $k->completionPct,
        ];
    }

    /** Latest approved % (hundredths) on or before the date; 0 when none. */
    private function pctAt(ProjectSnapshot $s, int $activityId, string $asOf, array $a): int
    {
        if ($a['pct_date'] !== null && $a['pct_date'] <= $asOf) {
            return (int) $a['pct'];
        }
        $pct = 0;
        foreach ($s->approvedHistory as $h) {
            if ($h['activity_id'] === $activityId && $h['record_date'] <= $asOf) {
                $pct = $h['pct'];
            }
        }
        return $pct;
    }

    public static function plannedFraction(string $start, string $finish, string $t): float
    {
        if ($t < $start) {
            return 0.0;
        }
        if ($t >= $finish) {
            return 1.0;
        }
        return self::days($start, $t) / max(1, self::days($start, $finish));
    }

    /** @param array<string, mixed> $a */
    public static function status(array $a, int $pctHundredths, string $t): ScheduleStatus
    {
        if ($pctHundredths >= 10000) {
            $finish = $a['actual_finish'] ?? $a['pct_date'] ?? $t;
            return $finish > $a['planned_finish'] ? ScheduleStatus::CompletedLate : ScheduleStatus::Completed;
        }
        if ($t > $a['planned_finish']) {
            return ScheduleStatus::Delayed;
        }
        if ($a['actual_start'] !== null || $pctHundredths > 0 || !empty($a['has_progress'])) {
            return ScheduleStatus::InProgress;
        }
        return $t > $a['planned_start'] ? ScheduleStatus::LateStart : ScheduleStatus::NotStarted;
    }

    public static function days(string $from, string $to): int
    {
        return (int) ((strtotime($to . ' 00:00:00 UTC') - strtotime($from . ' 00:00:00 UTC')) / 86400);
    }
}
