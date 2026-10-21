<?php

declare(strict_types=1);

namespace Lubie\Tests\Integration;

use Lubie\Db\Database;
use Lubie\Repositories\SettingsRepository;
use Lubie\Services\BackupService;
use Lubie\Services\KpiService;
use Lubie\Sms\SandboxSmsGateway;
use Lubie\Sms\SmsDispatcher;
use Lubie\Support\Clock;
use Lubie\Support\Env;
use Lubie\Tests\Support\IntegrationTestCase;

/** IT-ADM: users, settings, SMS caps, encrypted backup and restore verification (FR-05, FR-25, FR-35, FR-36). */
final class AdminBackupTest extends IntegrationTestCase
{
    public function testCreateUserValidatesAndAudits(): void
    {
        $admin = $this->as('admin');
        $bad = $admin->post('/api/v1/users', ['username' => 'Bad Name', 'full_name' => 'X', 'role' => 'boss', 'phone' => '12345', 'temporary_password' => 'short']);
        $this->assertSame(422, $bad['status']);
        foreach (['username', 'full_name', 'role', 'phone'] as $f) {
            $this->assertArrayHasKey($f, $bad['json']['error']['fields'], $f);
        }
        $ok = $admin->post('/api/v1/users', ['username' => 'qs3_demo', 'full_name' => 'Demo QS Three', 'role' => 'qs', 'phone' => '0200000099', 'temporary_password' => 'Start-here-2026']);
        $this->assertSame(201, $ok['status'], $ok['body']);
        $this->assertSame('+233200000099', $this->value("SELECT phone_e164 FROM users WHERE username = 'qs3_demo'"));
        $this->assertSame(1, (int) $this->value("SELECT must_change_password FROM users WHERE username = 'qs3_demo'"));
        $this->assertSame(422, $admin->post('/api/v1/users', ['username' => 'qs3_demo', 'full_name' => 'Dup', 'role' => 'qs', 'temporary_password' => 'Start-here-2026'])['status']);
        $this->assertSame(409, $admin->call('PATCH', '/api/v1/users/1', ['status' => 'deactivated'])['status'], 'Admin cannot lock themself out');
        $this->assertGreaterThan(0, (int) $this->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'create' AND entity_type = 'user'"));
    }

    public function testSettingsAreWhitelistedAndValidated(): void
    {
        $admin = $this->as('admin');
        $this->assertSame(404, $admin->call('PATCH', '/api/v1/settings/app_timezone', ['value' => 'Europe/London'])['status']);
        $this->assertSame(422, $admin->call('PATCH', '/api/v1/settings/refresh_interval_seconds', ['value' => '5'])['status']);
        $this->assertSame(204, $admin->call('PATCH', '/api/v1/settings/refresh_interval_seconds', ['value' => '45'])['status']);
        $this->assertSame(45, $this->as('pm1')->get('/api/v1/auth/me')['json']['data']['refresh_seconds']);
        $sms = $admin->post('/api/v1/settings/sms/test', ['phone' => '+233200000001']);
        $this->assertSame('sandbox', $sms['json']['data']['provider']);
        $this->assertStringContainsString('nothing was sent', $sms['json']['data']['note']);
    }

    public function testOutboxKeyOnlyForOfflineRolesAndStablePerUser(): void
    {
        $a = $this->as('se1')->get('/api/v1/auth/me')['json']['data']['outbox_key'];
        $b = $this->as('se1')->get('/api/v1/auth/me')['json']['data']['outbox_key'];
        $this->assertNotEmpty($a);
        $this->assertSame($a, $b, 'Same key after a new login, so queued items stay readable');
        $this->assertNotSame($a, $this->as('se3')->get('/api/v1/auth/me')['json']['data']['outbox_key']);
        $this->assertNull($this->as('client1')->get('/api/v1/auth/me')['json']['data']['outbox_key']);
        $this->assertSame(32, strlen(base64_decode($a, true)), 'AES-256 key');
    }

    public function testSmsDailyAndMonthlyCaps(): void
    {
        $db = self::$db;
        $db->run("UPDATE system_settings SET value = '1' WHERE setting_key = 'sms_enabled'");
        $db->run("UPDATE system_settings SET value = '2' WHERE setting_key = 'sms_daily_cap_per_user'");
        lubie_container($db)->get(\Lubie\Services\AlertService::class)->evaluate(1);
        $rid = (int) $db->value("SELECT r.id FROM notification_recipients r JOIN users u ON u.id = r.user_id WHERE u.username = 'pm1_demo' ORDER BY r.id LIMIT 1");
        $this->assertGreaterThan(0, $rid);
        $d = new SmsDispatcher($db, new SettingsRepository($db), new Clock(), new SandboxSmsGateway());
        $this->assertSame('sent', $d->dispatch($rid, '+233200000055', 'Test one'));
        $this->assertSame('sent', $d->dispatch($rid, '+233200000055', 'Test two'));
        $this->assertSame('capped', $d->dispatch($rid, '+233200000055', 'Test three'));
        $this->assertSame('sent', $d->dispatch($rid, '+233200000056', 'Other phone'));
        $db->run("UPDATE system_settings SET value = '1' WHERE setting_key = 'sms_monthly_cap_total'");
        $this->assertSame('capped', (new SmsDispatcher($db, new SettingsRepository($db), new Clock(), new SandboxSmsGateway()))->dispatch($rid, '+233200000057', 'Monthly'));
        $db->run("UPDATE system_settings SET value = '0' WHERE setting_key IN ('sms_enabled', 'sms_monthly_cap_total')");
        $this->assertSame('none', (new SmsDispatcher($db, new SettingsRepository($db), new Clock(), new SandboxSmsGateway()))->dispatch($rid, '+233200000058', 'Off'));
        $this->assertSame(0, (int) $db->value("SELECT COUNT(*) FROM sms_log s JOIN notification_recipients r ON r.id = s.recipient_id JOIN users u ON u.id = r.user_id JOIN roles ro ON ro.id = u.role_id WHERE ro.code = 'client'"),
            'No SMS row ever links to a client');
    }

    public function testEncryptedBackupRestoresWithMatchingCountsAndKpis(): void
    {
        if (strlen((string) Env::get('BACKUP_ENCRYPTION_KEY', '')) < 32) {
            $this->markTestSkipped('BACKUP_ENCRYPTION_KEY not set');
        }
        $admin = $this->as('admin');
        $r = $admin->post('/api/v1/admin/backups');
        $this->assertSame(200, $r['status'], substr($r['body'], 0, 300));
        $this->assertStringContainsString('attachment', $r['headers']['Content-Disposition']);
        $blob = $r['body'];
        $this->assertStringStartsWith('LUBIEBK1', $blob);
        $this->assertSame($r['headers']['X-Backup-SHA256'], hash('sha256', $blob));
        $this->assertStringNotContainsString('admin_demo', $blob, 'Content is encrypted');
        $plain = BackupService::decrypt($blob);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $plain, 'Sessions are never backed up');

        $target = Database::fromEnv('lubie_restore_test');
        try {
            $target->pdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('lubie_restore_test not available');
        }
        $target->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (array_column($target->all('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()'), 't') as $t) {
            $target->pdo()->exec("DROP TABLE `{$t}`");
        }
        $res = BackupService::restoreVerify($blob, $target, new KpiService(), new Clock());
        $this->assertTrue($res['ok'], json_encode($res));
        $this->assertGreaterThan(25, count($res['counts']));
        $this->assertCount(3, $res['kpis']);

        $tampered = substr($blob, 0, -1) . chr(ord($blob[-1]) ^ 1);
        $this->expectExceptionMessage('integrity check failed');
        BackupService::decrypt($tampered);
    }

    public function testSchedulerEndpointNeedsToken(): void
    {
        $c = $this->client();
        $this->assertSame(401, $c->call('POST', '/api/v1/backup/run', [], ['Authorization' => 'Bearer wrong'], false)['status']);
        $this->assertSame(401, $c->call('POST', '/api/v1/backup/run', [], [], false)['status']);
    }

    public function testAuditTrailFiltersAndPagination(): void
    {
        $admin = $this->as('admin');
        $r = $admin->get('/api/v1/audit?action=login&per_page=5&page=1');
        $this->assertSame(200, $r['status']);
        $this->assertLessThanOrEqual(5, count($r['json']['data']));
        $this->assertGreaterThan(0, $r['json']['meta']['total']);
        foreach ($r['json']['data'] as $row) {
            $this->assertSame('login', $row['action']);
            $this->assertStringNotContainsString('password', strtolower((string) $row['new_values']));
        }
    }
}
