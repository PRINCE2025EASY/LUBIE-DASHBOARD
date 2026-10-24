<?php

declare(strict_types=1);

namespace Lubie\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * UT-SEC: the hard access rules of the master prompt, checked against config/permissions.php
 * and the route table. These fail the build if someone widens access by mistake.
 */
final class PermissionMatrixTest extends TestCase
{
    /** @var array<string, list<string>> */
    private static array $m;

    public static function setUpBeforeClass(): void
    {
        self::$m = require dirname(__DIR__, 2) . '/config/permissions.php';
    }

    private function roles(string $perm): array
    {
        $this->assertArrayHasKey($perm, self::$m, "Unknown permission {$perm}");
        return self::$m[$perm];
    }

    public function testSiteEngineerNeverSeesMoney(): void
    {
        foreach (self::$m as $perm => $roles) {
            if (preg_match('/cost|budget|expenditure|certificate|payment|thresholds\.(global|decide)|reports|portfolio|audit|backup|users|settings/', $perm)) {
                $this->assertNotContains('site', $roles, $perm);
            }
        }
        $this->assertNotContains('site', $this->roles('activities.cost'));
    }

    public function testClientSeesOnlyTheClientSummary(): void
    {
        $allowed = ['password.change', 'projects.view', 'client.summary', 'alerts.view', 'reports.export', 'milestones.view'];
        foreach (self::$m as $perm => $roles) {
            if (in_array('client', $roles, true)) {
                $this->assertContains($perm, $allowed, "Client must not have {$perm}");
            }
        }
    }

    public function testAuditTrailIsAdministratorOnly(): void
    {
        $this->assertSame(['admin'], $this->roles('audit.view'), 'Decision 21/10/2026: Director has no audit trail');
    }

    public function testAdministratorHasNoFinancialDetail(): void
    {
        foreach (['cost.view', 'budget.request', 'budget.approve', 'expenditure.record', 'certificates.record', 'dashboard.project', 'dashboard.portfolio'] as $p) {
            $this->assertNotContains('admin', $this->roles($p), $p);
        }
    }

    public function testSeparationOfDuties(): void
    {
        $this->assertSame(['director'], $this->roles('budget.approve'));
        $this->assertNotContains('director', $this->roles('budget.request'));
        $this->assertSame(['pm'], $this->roles('progress.approve'));
    }

    public function testEveryRouteHasAKnownGuard(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/config/routes.php');
        preg_match_all("/\\\$r->add\\('(\\w+)', \"?'?([^\"',]+)\"?'?, \\[[^\\]]+\\], '([^']+)'\\)/", $src, $m, PREG_SET_ORDER);
        $this->assertGreaterThan(60, count($m), 'Route table parsed');
        foreach ($m as [, $method, $path, $guard]) {
            $parts = explode(',', $guard);
            $base = $parts[0];
            $this->assertTrue($base === 'public' || $base === 'auth' || str_starts_with($base, 'perm:'), "{$method} {$path}: {$guard}");
            if (str_starts_with($base, 'perm:')) {
                $this->assertArrayHasKey(substr($base, 5), self::$m, "{$method} {$path} uses unknown permission");
            }
            if ($base === 'public') {
                $this->assertContains($path, ['/', '/login', '/offline', '/health', '$v/auth/login', '$v/backup/run'], "Unexpected public route {$path}");
            }
            if (in_array('nocsrf', $parts, true)) {
                $this->assertSame('$v/backup/run', $path, 'Only the token-protected scheduler skips CSRF');
            }
        }
    }

    public function testEnvExampleHoldsNoSecrets(): void
    {
        $lines = file(dirname(__DIR__, 2) . '/.env.example', FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/^(APP_KEY|DB_PASSWORD|SMS_API_KEY|BACKUP_ENCRYPTION_KEY|BACKUP_TOKEN|DB_SSL_CA_PEM)=(.*)$/', trim($line), $m)) {
                $this->assertSame('', trim($m[2]), "{$m[1]} must stay empty in .env.example");
            }
        }
    }

    public function testServiceWorkerVersionFollowsAssetVersion(): void
    {
        $root = dirname(__DIR__, 2);
        preg_match("/ASSET_VERSION = '([0-9.]+)'/", (string) file_get_contents($root . '/src/Views/View.php'), $a);
        preg_match("/const VERSION = 'lubie-v([0-9.]+)'/", (string) file_get_contents($root . '/public/sw.js'), $b);
        $this->assertSame($a[1], $b[1], 'Bump public/sw.js VERSION with every asset version, or browsers keep old files');
    }
}
