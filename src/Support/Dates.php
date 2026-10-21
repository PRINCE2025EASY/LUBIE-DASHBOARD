<?php

declare(strict_types=1);

namespace Lubie\Support;

/** Display helpers: dates are stored ISO (YYYY-MM-DD) and shown DD/MM/YYYY. */
final class Dates
{
    public static function uk(?string $iso): string
    {
        if ($iso === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
            return (string) $iso;
        }
        return "{$m[3]}/{$m[2]}/{$m[1]}";
    }
}
