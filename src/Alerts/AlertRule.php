<?php

declare(strict_types=1);

namespace Lubie\Alerts;

use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** Strategy interface: one class per alert rule (open for extension, closed for change). */
interface AlertRule
{
    /** @return list<Condition> conditions that are true now */
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array;
}
