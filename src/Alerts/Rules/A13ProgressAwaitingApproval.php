<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** A13: progress entries awaiting PM approval (one alert per project). */
final class A13ProgressAwaitingApproval implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $n = count($s->submittedProgress);
        if ($n === 0) {
            return [];
        }
        return [new Condition('A13', "A13:project:{$s->id()}", 'information', 'project', $s->id(),
            'Progress waiting for approval', "{$s->code()} has progress entries waiting for your approval.", ['pm'])];
    }
}
