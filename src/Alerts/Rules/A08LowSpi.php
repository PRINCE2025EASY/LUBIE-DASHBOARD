<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** A8: SPI below s (default 0.90) when PV > 0. */
final class A08LowSpi implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        if ($k->spi === null || $k->spi >= $t->get('spi_min')) {
            return [];
        }
        return [new Condition('A8', "A8:project:{$s->id()}", 'warning', 'project', $s->id(),
            "Schedule performance low (SPI {$k->spi})", "{$s->code()} has earned {$k->spi} of the planned value to date (limit " . $t->get('spi_min') . ').', ['pm'])];
    }
}
