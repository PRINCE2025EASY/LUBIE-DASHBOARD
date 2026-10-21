<?php

declare(strict_types=1);

namespace Lubie\Tests\Integration;

use Lubie\Tests\Support\IntegrationTestCase;

/** ST: attack-style probes (OWASP Top 10 mapping in docs/TEST_REPORT.md). */
final class SecurityProbeTest extends IntegrationTestCase
{
    public function testSqlInjectionPayloadsAreRejectedOrInert(): void
    {
        $pm = $this->as('pm1');
        foreach (["1 OR 1=1", "1;DROP TABLE users", "1' OR '1'='1", '-1 UNION SELECT 1'] as $p) {
            $r = $pm->get('/api/v1/projects/' . rawurlencode($p) . '/dashboard');
            $this->assertSame(404, $r['status'], $p);
        }
        $admin = $this->as('admin');
        $r = $admin->get("/api/v1/audit?user=" . rawurlencode("x' OR '1'='1") . '&action=' . rawurlencode("login' -- "));
        $this->assertSame(200, $r['status']);
        $this->assertSame(0, $r['json']['meta']['total'], 'Filter values are bound parameters, not SQL');
        $c = $this->client();
        $this->assertSame(401, $c->login("admin_demo' -- ", 'x')['status']);
        $this->assertSame(13, (int) $this->value('SELECT COUNT(*) FROM users'), 'Users table intact');
    }

    public function testStoredXssIsEscapedInReports(): void
    {
        $pm = $this->as('pm1');
        $xss = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $r = $pm->post('/api/v1/projects/1/milestones', ['name' => 'MS ' . $xss, 'due_date' => date('Y-m-d', strtotime('+30 days'))]);
        $this->assertSame(201, $r['status'], $r['body']);
        $html = $pm->get('/api/v1/reports/milestones?project_id=1&format=html');
        $this->assertSame(200, $html['status']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html['body']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html['body']);
        $this->assertStringContainsString("default-src 'none'", $html['headers']['Content-Security-Policy']);
        $json = $pm->get('/api/v1/projects/1/milestones');
        $this->assertSame('application/json; charset=utf-8', $json['headers']['Content-Type'], 'API never serves HTML');
    }

    public function testOpenRedirectBlockedAfterLogin(): void
    {
        foreach (['https://evil.example/', '//evil.example', '/\\evil.example', '/api/v1/users', '/admin'] as $next) {
            $c = $this->client();
            $page = $c->get('/login');
            preg_match('/name="csrf-token" content="([0-9a-f]+)"/', $page['body'], $m);
            $c->csrf = $m[1];
            $r = $c->post('/api/v1/auth/login', ['username' => 'pm1_demo', 'password' => self::$pw, 'next' => $next]);
            $this->assertSame('/pm', $r['json']['data']['redirect'], "next={$next}");
        }
    }

    public function testSessionCookieFlags(): void
    {
        $c = $this->client();
        $c->get('/login');
        $c->login('pm1_demo', self::$pw);
        $cookie = implode("\n", $c->last->cookies);
        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringContainsString('SameSite=Lax', $cookie);
        $this->assertMatchesRegularExpression('/lubie_sid=[a-f0-9]{64};/', $cookie, '256-bit random session ID');
    }

    public function testForgedSessionIdIsIgnored(): void
    {
        $c = $this->client();
        $r = $c->call('GET', '/api/v1/auth/me', null, ['cookie' => 'lubie_sid=' . str_repeat('a', 64)]);
        $this->assertSame(401, $r['status']);
    }

    public function testMethodNotAllowedAndUnknownRoutes(): void
    {
        $pm = $this->as('pm1');
        $this->assertSame(405, $pm->call('DELETE', '/api/v1/projects')['status']);
        $this->assertSame(404, $pm->get('/api/v1/nothing-here')['status']);
        $this->assertSame(404, $pm->get('/api/v1/reports/../../.env')['status']);
    }

    public function testErrorsDoNotLeakInternals(): void
    {
        $pm = $this->as('pm1');
        $r = $pm->post('/api/v1/activities/999999/progress', ['record_date' => date('Y-m-d'), 'percent_complete' => 5]);
        $this->assertSame(404, $r['status']);
        $this->assertStringNotContainsString('SQLSTATE', $r['body']);
        $this->assertStringNotContainsString('/src/', $r['body']);
        $this->assertArrayHasKey('request_id', $r['json']['error']);
    }

    public function testMassAssignmentIgnored(): void
    {
        $se = $this->as('se1');
        $r = $se->post('/api/v1/activities/5/progress', ['record_date' => date('Y-m-d'), 'percent_complete' => 15, 'status' => 'approved', 'reviewed_by' => 3, 'submitted_by' => 3]);
        $this->assertSame(201, $r['status']);
        $row = self::$db->one('SELECT status, submitted_by, reviewed_by FROM progress_records WHERE id = ?', [$r['json']['data']['id']]);
        $this->assertSame('submitted', $row['status']);
        $this->assertSame(7, (int) $row['submitted_by']);
        $this->assertNull($row['reviewed_by']);
    }

    public function testAuditTrailCannotBeChangedThroughTheApp(): void
    {
        $admin = $this->as('admin');
        foreach (['DELETE', 'PATCH', 'PUT'] as $m) {
            $this->assertContains($admin->call($m, '/api/v1/audit', [])['status'], [404, 405], $m);
        }
    }
}
