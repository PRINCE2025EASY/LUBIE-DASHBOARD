<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Repositories\ProjectSnapshotRepository;
use Lubie\Support\Clock;
use Lubie\Support\Env;
use RuntimeException;

/**
 * Daily full logical backup (FR-36, NFR-06): SQL dump -> gzip -> AES-256-GCM, streamed to
 * the caller (Administrator download or external scheduler). Nothing is kept on the web
 * server's disk. restoreVerify() imports into a separate database and compares row counts
 * and KPI values with the figures embedded in the backup.
 */
final class BackupService
{
    private const MAGIC = 'LUBIEBK1';
    /** Structure only, no rows. */
    private const SKIP_TABLES = ['sessions'];

    public function __construct(private Database $db, private AuditLogger $audit, private Clock $clock, private ProjectSnapshotRepository $snaps, private KpiService $kpi)
    {
    }

    /** @return array{file:string, bytes:string, sha256:string, counts:array<string,int>} */
    public function create(?int $userId, string $kind): array
    {
        $key = self::key();
        $logId = $this->db->insert("INSERT INTO backup_log (kind, status, triggered_by, started_at) VALUES (?, 'started', ?, ?)", [$kind, $userId, $this->clock->nowSql()]);
        try {
            [$sql, $counts] = $this->dump();
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt((string) gzencode($sql, 6), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher === false) {
                throw new RuntimeException('Encryption failed');
            }
            $blob = self::MAGIC . $iv . $tag . $cipher;
            $sha = hash('sha256', $blob);
            $name = 'lubie_' . (Env::get('APP_ENV', 'local')) . '_' . $this->clock->now()->format('Ymd_Hi') . '.sql.gz.enc';
            $weekly = $this->clock->now()->format('N') === '7' ? 'weekly' : 'daily';
            $this->db->run("UPDATE backup_log SET status = 'succeeded', file_name = ?, byte_size = ?, sha256 = ?, retention = ?, row_counts = ?, finished_at = ? WHERE id = ?",
                [$name, strlen($blob), $sha, $weekly, json_encode($counts), $this->clock->nowSql(), $logId]);
            $this->audit->log($userId, 'backup', 'backup', (string) $logId, null, ['kind' => $kind, 'file' => $name, 'bytes' => strlen($blob)]);
            return ['file' => $name, 'bytes' => $blob, 'sha256' => $sha, 'counts' => $counts];
        } catch (\Throwable $e) {
            $this->db->run("UPDATE backup_log SET status = 'failed', notes = ?, finished_at = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 300), $this->clock->nowSql(), $logId]);
            throw $e;
        }
    }

    /** Token check for the external scheduler (constant-time; at most one run per 10 minutes). */
    public function authoriseScheduler(?string $authHeader): void
    {
        $expected = Env::get('BACKUP_TOKEN');
        $given = $authHeader !== null && str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
        if ($expected === null || strlen($expected) < 32 || !hash_equals($expected, $given)) {
            $this->audit->log(null, 'forbidden', 'backup', 'scheduler');
            throw HttpException::unauthenticated();
        }
        $recent = $this->db->value("SELECT 1 FROM backup_log WHERE kind = 'scheduled' AND started_at > ?", [$this->clock->now()->modify('-10 minutes')->format('Y-m-d H:i:s')]);
        if ($recent) {
            throw HttpException::tooMany('A backup ran less than 10 minutes ago.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function log(): array
    {
        return $this->db->all('SELECT b.*, u.username FROM backup_log b LEFT JOIN users u ON u.id = b.triggered_by ORDER BY b.id DESC LIMIT 60');
    }

    /** @return array{0:string, 1:array<string,int>} */
    public function dump(): array
    {
        $tables = array_column($this->db->all("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name"), 't');
        $counts = [];
        $kpis = [];
        foreach ($this->db->all("SELECT id FROM projects") as $p) {
            $k = $this->kpi->compute($this->snaps->load((int) $p['id']), $this->clock->today());
            $kpis[(int) $p['id']] = ['completion' => $k->completionPct, 'ac' => $k->ac];
        }
        $out = ['-- Lubie dashboard backup ' . $this->clock->now()->format('c'), '-- KPI ' . json_encode($kpis), 'SET NAMES utf8mb4;', 'SET FOREIGN_KEY_CHECKS = 0;'];
        foreach ($tables as $t) {
            $create = $this->db->one("SHOW CREATE TABLE `{$t}`");
            $out[] = 'DROP TABLE IF EXISTS `' . $t . '`;';
            $out[] = str_replace("\n", ' ', (string) array_values($create)[1]) . ';';
            if (in_array($t, self::SKIP_TABLES, true)) {
                continue; // structure only: session contents are never backed up
            }
            $cols = array_column($this->db->all("SELECT column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?
                                                AND extra NOT IN ('STORED GENERATED', 'VIRTUAL GENERATED', 'PERSISTENT GENERATED') ORDER BY ordinal_position", [$t]), 'c');
            $colList = '`' . implode('`,`', $cols) . '`';
            $n = 0;
            $batch = [];
            foreach ($this->db->run("SELECT {$colList} FROM `{$t}`")->getIterator() as $row) {
                $batch[] = '(' . implode(',', array_map([self::class, 'lit'], array_values($row))) . ')';
                $n++;
                if (count($batch) === 200) {
                    $out[] = "INSERT INTO `{$t}` ({$colList}) VALUES " . implode(',', $batch) . ';';
                    $batch = [];
                }
            }
            if ($batch) {
                $out[] = "INSERT INTO `{$t}` ({$colList}) VALUES " . implode(',', $batch) . ';';
            }
            $counts[$t] = $n;
        }
        $out[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $out[] = '-- COUNTS ' . json_encode($counts);
        return [implode("\n", $out) . "\n", $counts];
    }

    public static function lit(mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        return "'" . strtr((string) $v, ["\\" => "\\\\", "'" => "\\'", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "\x1a" => "\\Z"]) . "'";
    }

    public static function decrypt(string $blob): string
    {
        if (!str_starts_with($blob, self::MAGIC)) {
            throw new RuntimeException('Not a Lubie backup file.');
        }
        $iv = substr($blob, 8, 12);
        $tag = substr($blob, 20, 16);
        $plain = openssl_decrypt(substr($blob, 36), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Wrong key or damaged file (integrity check failed).');
        }
        $sql = gzdecode($plain);
        if ($sql === false) {
            throw new RuntimeException('Backup content is not valid gzip.');
        }
        return $sql;
    }

    /**
     * Import into an EMPTY separate database and verify (FT-32).
     * @return array{ok:bool, seconds:float, counts:array<string,array{expected:int, actual:int}>, kpis:array<int, array<string, mixed>>}
     */
    public static function restoreVerify(string $blob, Database $target, KpiService $kpi, Clock $clock): array
    {
        $t0 = microtime(true);
        $sql = self::decrypt($blob);
        $lines = explode("\n", $sql);
        $expected = [];
        $expectedKpi = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '-- COUNTS ')) {
                $expected = json_decode(substr($line, 10), true) ?: [];
            } elseif (str_starts_with($line, '-- KPI ')) {
                $expectedKpi = json_decode(substr($line, 7), true) ?: [];
            } elseif ($line !== '' && !str_starts_with($line, '--')) {
                $target->pdo()->exec($line);
            }
        }
        $counts = [];
        $ok = true;
        foreach ($expected as $t => $n) {
            $actual = (int) $target->value("SELECT COUNT(*) FROM `{$t}`");
            $counts[$t] = ['expected' => (int) $n, 'actual' => $actual];
            $ok = $ok && $actual === (int) $n;
        }
        $snaps = new ProjectSnapshotRepository($target);
        $kres = [];
        foreach ($expectedKpi as $pid => $exp) {
            $k = $kpi->compute($snaps->load((int) $pid), $clock->today());
            // JSON turns 100.0 into 100: compare as floats
            $expPct = $exp['completion'] === null ? null : (float) $exp['completion'];
            $match = $k->completionPct === $expPct && $k->ac === (int) $exp['ac'];
            $kres[(int) $pid] = ['expected' => $exp, 'actual' => ['completion' => $k->completionPct, 'ac' => $k->ac], 'match' => $match];
            $ok = $ok && $match;
        }
        return ['ok' => $ok, 'seconds' => round(microtime(true) - $t0, 2), 'counts' => $counts, 'kpis' => $kres];
    }

    private static function key(): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new HttpException(503, 'BACKUP_NOT_CONFIGURED', 'Backups need the PHP openssl extension. Enable extension=openssl in php.ini and restart Apache.');
        }
        $k = Env::get('BACKUP_ENCRYPTION_KEY');
        if ($k === null || strlen($k) < 32) {
            throw new HttpException(503, 'BACKUP_NOT_CONFIGURED', 'Backups need BACKUP_ENCRYPTION_KEY (32+ characters) in the environment.');
        }
        return hash('sha256', $k, true);
    }
}
