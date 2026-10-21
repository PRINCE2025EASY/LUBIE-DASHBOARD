<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Support\Dates;

/**
 * A15: work stoppage or safety incident selected as delay reason. Stays open until a later
 * approved progress entry on the same activity shows work has resumed. Not a safety module.
 */
final class A15StoppageReported implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $out = [];
        foreach ($s->criticalReports as $r) {
            $resumed = false;
            foreach ($s->approvedHistory as $h) {
                if ($h['activity_id'] === $r['activity_id'] && $h['record_date'] > $r['record_date']) {
                    $resumed = true;
                }
            }
            if (!$resumed) {
                $out[] = new Condition('A15', "A15:progress:{$r['id']}", 'critical', 'progress', $r['id'],
                    "{$r['reason']} reported", "{$s->code()}: {$r['reason']} reported on " . Dates::uk($r['record_date']) . ".",
                    ['director', 'pm', 'qs'], ['director', 'pm', 'qs']);
            }
        }
        return $out;
    }
}
