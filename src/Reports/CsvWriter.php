<?php

declare(strict_types=1);

namespace Lubie\Reports;

/** CSV output with UTF-8 BOM (Excel) and formula-injection protection (ST-15). */
final class CsvWriter
{
    /**
     * @param list<string> $headers
     * @param iterable<list<scalar|null>> $rows
     */
    public static function write($out, array $headers, iterable $rows): void
    {
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($out, array_map([self::class, 'safe'], $r), ',', '"', '');
        }
    }

    public static function safe(mixed $v): string
    {
        $s = $v === null ? '' : (string) $v;
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s)) {
            return "'" . $s;
        }
        return $s;
    }
}
