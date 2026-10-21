<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Services\KpiService;

/** A11: no approved progress for d days (default 7) on a started project. */
final class A11StaleData implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $d = (int) $t->get('stale_data_days');
        $since = $k->dataCurrencyDays ?? KpiService::days((string) $s->project['planned_start'], $today);
        if ($since < $d || $today < (string) $s->project['planned_start']) {
            return [];
        }
        $what = $s->lastApprovedProgressDate === null ? 'No approved progress has been recorded yet' : "No approved progress for {$since} days";
        return [new Condition('A11', "A11:project:{$s->id()}", 'warning', 'project', $s->id(), 'Project data is out of date',
            "{$s->code()}: {$what}.", ['pm', 'site'])];
    }
}
