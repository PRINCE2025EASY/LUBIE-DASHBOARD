<?php

declare(strict_types=1);

namespace Lubie\Sms;

use Lubie\Db\Database;
use Lubie\Repositories\SettingsRepository;
use Lubie\Support\Clock;

/**
 * Sends one SMS per alert recipient within the per-user daily cap and optional monthly
 * company cap. Synchronous with a short timeout (no worker on free hosts); failures are
 * retried on the next alert evaluation, up to 3 attempts.
 */
final class SmsDispatcher
{
    public function __construct(private Database $db, private SettingsRepository $settings, private Clock $clock, private SmsGateway $gateway)
    {
    }

    public function dispatch(int $recipientId, ?string $phone, string $message): string
    {
        if (!$this->settings->bool('sms_enabled') || $phone === null) {
            return 'none';
        }
        $msg = mb_substr($message, 0, 160);
        $today = $this->clock->today();
        $daily = (int) $this->db->value(
            "SELECT COUNT(*) FROM sms_log WHERE phone_e164 = ? AND status IN ('sent','queued') AND created_at >= ?", [$phone, $today . ' 00:00:00']);
        $monthlyCap = $this->settings->int('sms_monthly_cap_total', 0);
        $monthly = $monthlyCap > 0 ? (int) $this->db->value(
            "SELECT COUNT(*) FROM sms_log WHERE status IN ('sent','queued') AND created_at >= ?", [substr($today, 0, 7) . '-01 00:00:00']) : 0;
        if ($daily >= $this->settings->int('sms_daily_cap_per_user', 5) || ($monthlyCap > 0 && $monthly >= $monthlyCap)) {
            $this->log($recipientId, $phone, $msg, 'capped', null, 'cap reached');
            return 'capped';
        }
        $r = $this->gateway->send($phone, $msg);
        $status = $r['ok'] ? 'sent' : 'failed';
        $this->log($recipientId, $phone, $msg, $status, $r['ref'], $r['error'], $r['ok'] ? 0 : 1);
        return $status;
    }

    /** Retry failed messages flagged retry_due (called during alert evaluation). */
    public function retryDue(): void
    {
        foreach ($this->db->all('SELECT id, recipient_id, phone_e164, message, attempts FROM sms_log WHERE retry_due = 1 AND attempts < 3 LIMIT 20') as $row) {
            $r = $this->gateway->send((string) $row['phone_e164'], (string) $row['message']);
            $this->db->run('UPDATE sms_log SET attempts = attempts + 1, status = ?, provider_ref = ?, error_text = ?, retry_due = ?, sent_at = ? WHERE id = ?',
                [$r['ok'] ? 'sent' : 'failed', $r['ref'], $r['error'], $r['ok'] || (int) $row['attempts'] + 1 >= 3 ? 0 : 1, $r['ok'] ? $this->clock->nowSql() : null, $row['id']]);
            $this->db->run('UPDATE notification_recipients SET sms_status = ? WHERE id = ?', [$r['ok'] ? 'sent' : 'failed', $row['recipient_id']]);
        }
    }

    private function log(int $recipientId, string $phone, string $msg, string $status, ?string $ref, ?string $error, int $retry = 0): void
    {
        $now = $this->clock->nowSql();
        $this->db->run('INSERT INTO sms_log (recipient_id, phone_e164, message, provider, status, provider_ref, error_text, attempts, retry_due, created_at, sent_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$recipientId, $phone, $msg, $this->gateway->name(), $status, $ref, $error, $retry, $now, $status === 'sent' ? $now : null]);
    }
}
