<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** A1: today after planned finish and under 100 %. SMS level when the delay exceeds delay_sms_days. */
final class A01ActivityDelayed implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $out = [];
        foreach ($k->delayedActivities() as $a) {
            $out[] = new Condition('A1', "A1:activity:{$a['id']}", 'high', 'activity', $a['id'],
                "Activity {$a['code']} delayed",
                "{$s->code()} {$a['code']} {$a['name']} is {$a['days_late']} days past its planned finish at {$a['pct']}% complete.",
                ['pm', 'site']);
            if ($a['days_late'] > $t->get('delay_sms_days')) {
                $out[] = new Condition('A1', "A1S:activity:{$a['id']}", 'high', 'activity', $a['id'],
                    "Activity {$a['code']} delayed more than " . (int) $t->get('delay_sms_days') . ' days',
                    "{$s->code()} {$a['code']} delayed {$a['days_late']} days.", ['pm', 'site', 'director'], ['director', 'pm']);
            }
        }
        return $out;
    }
}
