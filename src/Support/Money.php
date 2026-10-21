<?php

declare(strict_types=1);

namespace Lubie\Support;

use InvalidArgumentException;

/**
 * Money is handled as integer pesewas (GH₵ 1 = 100 pesewas) to avoid float errors.
 * The database stores DECIMAL(15,2); the API sends strings with two decimals.
 */
final class Money
{
    public static function toPesewas(string|int|float|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = is_string($value) ? trim(str_replace([',', ' '], '', $value)) : (string) $value;
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $s)) {
            throw new InvalidArgumentException('Invalid amount: use digits with up to two decimals.');
        }
        $neg = str_starts_with($s, '-');
        $s = ltrim($s, '-');
        [$whole, $frac] = array_pad(explode('.', $s, 2), 2, '0');
        $frac = str_pad($frac, 2, '0');
        $p = (int) $whole * 100 + (int) $frac;
        return $neg ? -$p : $p;
    }

    public static function toString(?int $pesewas): ?string
    {
        if ($pesewas === null) {
            return null;
        }
        $neg = $pesewas < 0;
        $a = abs($pesewas);
        return ($neg ? '-' : '') . intdiv($a, 100) . '.' . str_pad((string) ($a % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Display form, e.g. GH₵ 2,400,000.00 */
    public static function format(?int $pesewas): string
    {
        if ($pesewas === null) {
            return 'Not available';
        }
        $neg = $pesewas < 0;
        $a = abs($pesewas);
        return ($neg ? '-' : '') . 'GH₵ ' . number_format(intdiv($a, 100)) . '.' . str_pad((string) ($a % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Round half away from zero for (a * num / den) on integers. */
    public static function mulDiv(int $a, int $num, int $den): int
    {
        if ($den === 0) {
            throw new InvalidArgumentException('Division by zero');
        }
        $p = $a * $num;
        $q = intdiv($p, $den);
        $r = $p % $den;
        if (abs($r) * 2 >= abs($den)) {
            $q += ($p >= 0) === ($den > 0) ? 1 : -1;
        }
        return $q;
    }
}
