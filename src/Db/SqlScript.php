<?php

declare(strict_types=1);

namespace Lubie\Db;

use RuntimeException;

/**
 * Splits a .sql file into single statements and runs them one by one, so a failure
 * stops at the exact statement (PDO multi-statement exec can hide later errors).
 * Handles quotes, backticks, line and block comments. No DELIMITER support (none used).
 */
final class SqlScript
{
    /** @return list<string> */
    public static function split(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';
            if ($quote !== null) {
                $buf .= $ch;
                if ($ch === '\\' && $quote !== '`') {
                    $buf .= $next;
                    $i++;
                } elseif ($ch === $quote) {
                    if ($next === $quote) {
                        $buf .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                $buf .= "\n";
                continue;
            }
            if ($ch === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                $buf .= "\n";
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                $buf .= ' ';
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if ($quote !== null) {
            throw new RuntimeException('Unterminated quote in SQL script.');
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }

    /** @return int number of statements run */
    public static function runFile(Database $db, string $path): int
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Cannot read ' . $path);
        }
        $n = 0;
        foreach (self::split($sql) as $stmt) {
            try {
                $db->pdo()->exec($stmt);
            } catch (\PDOException $e) {
                throw new RuntimeException(sprintf('%s, statement %d failed: %s', basename($path), $n + 1, $e->getMessage()), 0, $e);
            }
            $n++;
        }
        return $n;
    }
}
