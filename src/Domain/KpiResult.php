<?php

declare(strict_types=1);

namespace Lubie\Domain;

/**
 * Calculated indicators. Null means "not available" (zero denominator or missing data);
 * the UI shows a text explanation instead of a misleading number.
 */
final class KpiResult
{
    /**
     * @param 'cost'|'duration'|'none' $weighting
     * @param list<array<string, mixed>> $activities per-activity status rows
     */
    public function __construct(
        public readonly string $asOf,
        public readonly string $weighting,
        public readonly ?int $bac,
        public readonly ?int $pv,
        public readonly ?int $ev,
        public readonly int $ac,
        public readonly ?float $completionPct,
        public readonly ?float $plannedPct,
        public readonly ?int $sv,
        public readonly ?float $spi,
        public readonly ?int $cv,
        public readonly ?float $cpi,
        public readonly ?int $currentBudget,
        public readonly ?int $budgetVariance,
        public readonly ?float $budgetConsumedPct,
        public readonly ?int $dataCurrencyDays,
        public readonly int $daysToPlannedFinish,
        public readonly array $activities,
    ) {
    }

    public function delayedActivities(): array
    {
        return array_values(array_filter($this->activities, static fn (array $a) => $a['status'] === ScheduleStatus::Delayed->value));
    }
}
