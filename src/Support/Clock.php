<?php

declare(strict_types=1);

namespace Lubie\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Time source. Business dates use Africa/Accra (UTC+0, no daylight saving).
 * Injected everywhere so tests can fix "today".
 */
class Clock
{
    private ?DateTimeImmutable $fixed;

    public function __construct(?string $fixedNow = null)
    {
        $this->fixed = $fixedNow !== null ? new DateTimeImmutable($fixedNow, self::zone()) : null;
    }

    public static function zone(): DateTimeZone
    {
        return new DateTimeZone('Africa/Accra');
    }

    public function now(): DateTimeImmutable
    {
        return $this->fixed ?? new DateTimeImmutable('now', self::zone());
    }

    public function today(): string
    {
        return $this->now()->format('Y-m-d');
    }

    public function nowSql(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }
}
