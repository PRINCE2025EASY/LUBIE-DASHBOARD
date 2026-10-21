<?php

declare(strict_types=1);

namespace Lubie\Alerts;

/** Effective thresholds for one project: global (Director) values overridden by project (PM) values. */
final class Thresholds
{
    /** @param array<string, float> $values */
    public function __construct(private array $values)
    {
    }

    public static function defaults(): self
    {
        return new self([
            'budget_warning_pct' => 80.0, 'budget_overrun_sms_pct' => 10.0, 'cpi_min' => 0.90, 'spi_min' => 0.90,
            'milestone_due_days' => 7.0, 'stale_data_days' => 7.0, 'delay_sms_days' => 14.0,
        ]);
    }

    public function get(string $key): float
    {
        return $this->values[$key] ?? self::defaults()->values[$key] ?? 0.0;
    }

    /** @return array<string, float> */
    public function all(): array
    {
        return $this->values + self::defaults()->values;
    }
}
