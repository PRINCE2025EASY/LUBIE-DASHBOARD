<?php

declare(strict_types=1);

namespace Lubie\Tests\Integration;

use Lubie\Tests\Support\IntegrationTestCase;

/** IT-AUTH: login, redirect, lockout, throttling, CSRF, sessions, forced password change (FR-01, FR-02, FR-29, NFR-01). */
final class AuthSecurityTest extends IntegrationTestCase
{
    public function testUnifiedLoginRedirectsEachRoleToItsOwnDashboard(): void
    {
        foreach (['admin' => '/admin', 'director' => '/director', 'pm1' => '/pm', 'qs1' => '/qs', 'se1' => '/site', 'client1' => '/client'] as $u => $path) {
            $c = $this->client();
            $r = $c->login($u . '_demo', self::$pw);
            $this->assertSame(200, $r['status'], $u);
            $this->assertSame($path, $r['json']['data']['redirect'], $u);
            $this->assertSame(302, $c->get('/')['status']);
            $this->assertSame(200, $c->get($path)['status'], "{$u} opens own page");
        }
    }

    public function testRoleComesFromServerNotFromBrowser(): void
    {
        $c = $this->client();
        $r = $c->post('/api/v1/auth/login', ['username' => 'se1_demo', 'password' => self::$pw, 'role' => 'director']);
        // first request had no CSRF token from a page load: login still requires one
        $this->assertSame(403, $r['status']);
        $c = $this->as('se1');
        $this->assertSame('site', $c->get('/api/v1/auth/me')['json']['data']['role']);
        $this->assertSame(403, $c->get('/api/v1/dashboard/portfolio')['status']);
    }

    public function testOtherRolePageIsRefusedAndAudited(): void
    {
        $before = (int) $this->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'forbidden' AND entity_type = 'page'");
        $c = $this->as('se1');
        $this->assertSame(403, $c->get('/director')['status']);
        $this->assertSame(403, $c->get('/admin')['status']);
        $this->assertSame($before + 2, (int) $this->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'forbidden' AND entity_type = 'page'"));
    }

    public function testGenericErrorForUnknownUserAndWrongPassword(): void
    {
        $c = $this->client();
        $a = $c->login('nobody_here', 'Whatever123');
        $b = $c->login('pm2_demo', 'Wrong-password-1');
        $this->assertSame(401, $a['status']);
        $this->assertSame(401, $b['status']);
        $this->assertSame($a['json']['error']['message'], $b['json']['error']['message'], 'No username enumeration');
    }

    public function testLockoutAfterFiveFailuresAndDeactivatedUserRefused(): void
    {
        $c = $this->client();
        for ($i = 0; $i < 5; $i++) {
            $c->login('qs2_demo', 'Wrong-password-' . $i);
        }
        $this->assertSame('locked', $this->value("SELECT status FROM users WHERE username = 'qs2_demo'"));
        $this->assertSame(401, $c->login('qs2_demo', self::$pw)['status'], 'Correct password refused while locked');
        $this->assertSame(401, $this->client()->login('se4_demo', self::$pw)['status'], 'Deactivated account cannot log in');
        // Administrator unlocks
        $admin = $this->as('admin');
        $id = (int) $this->value("SELECT id FROM users WHERE username = 'qs2_demo'");
        $this->assertSame(204, $admin->post("/api/v1/users/{$id}/unlock")['status']);
        $this->assertSame(200, $this->client()->login('qs2_demo', self::$pw)['status']);
    }

    public function testIpThrottleAfterTwentyFailures(): void
    {
        $c = $this->client('192.0.2.77');
        for ($i = 0; $i < 20; $i++) {
            $c->login('ghost' . $i, 'Bad-password-1');
        }
        $this->assertSame(429, $c->login('pm1_demo', self::$pw)['status']);
    }

    public function testCsrfRequiredOnStateChangingRequests(): void
    {
        $c = $this->as('pm1');
        $r = $c->call('POST', '/api/v1/auth/theme', ['theme' => 'dark'], [], false);
        $this->assertSame(403, $r['status']);
        $this->assertSame('CSRF_FAILED', $r['json']['error']['code']);
        $r = $c->call('POST', '/api/v1/auth/theme', ['theme' => 'dark'], ['X-CSRF-Token' => str_repeat('a', 64)], false);
        $this->assertSame(403, $r['status']);
        $this->assertSame(204, $c->post('/api/v1/auth/theme', ['theme' => 'dark'])['status']);
    }

    public function testSessionIdChangesAtLoginAndLogoutEndsSession(): void
    {
        $c = $this->client();
        $c->get('/login');
        $before = $c->sessionId();
        $c->login('pm1_demo', self::$pw);
        $after = $c->sessionId();
        $this->assertNotNull($after);
        $this->assertNotSame($before, $after, 'Session fixation protection');
        $this->assertSame(200, $c->post('/api/v1/auth/logout')['status']);
        $this->assertSame(401, $c->get('/api/v1/auth/me')['status']);
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM sessions WHERE id = ?', [$after]));
    }

    public function testApiReturns401AndPagesRedirectWhenNotLoggedIn(): void
    {
        $c = $this->client();
        $this->assertSame(401, $c->get('/api/v1/projects')['status']);
        $r = $c->get('/pm');
        $this->assertSame(302, $r['status']);
        $this->assertStringContainsString('/login', $r['headers']['Location']);
    }

    public function testForcedPasswordChangeAfterAdminReset(): void
    {
        $admin = $this->as('admin');
        $id = (int) $this->value("SELECT id FROM users WHERE username = 'client3_demo'");
        $victim = $this->as('client3');
        $this->assertSame(204, $admin->post("/api/v1/users/{$id}/reset-password", ['temporary_password' => 'Temporary-2026x'])['status']);
        $this->assertSame(401, $victim->get('/api/v1/auth/me')['status'], 'Reset signs the user out everywhere');
        $c = $this->client();
        $r = $c->login('client3_demo', 'Temporary-2026x');
        $this->assertSame('/password', $r['json']['data']['redirect']);
        $this->assertSame('PASSWORD_CHANGE_REQUIRED', $c->get('/api/v1/projects')['json']['error']['code']);
        $weak = $c->post('/api/v1/auth/password', ['current_password' => 'Temporary-2026x', 'new_password' => 'short', 'confirm_password' => 'short']);
        $this->assertSame(422, $weak['status']);
        $ok = $c->post('/api/v1/auth/password', ['current_password' => 'Temporary-2026x', 'new_password' => 'Brand-new-pass-77', 'confirm_password' => 'Brand-new-pass-77']);
        $this->assertSame(200, $ok['status']);
        $this->assertSame(200, $c->get('/api/v1/projects')['status']);
    }

    public function testSecurityHeadersPresent(): void
    {
        $r = $this->client()->get('/login');
        $this->assertStringContainsString("script-src 'self'", $r['headers']['Content-Security-Policy']);
        $this->assertStringNotContainsString('unsafe-inline', $r['headers']['Content-Security-Policy']);
        $this->assertSame('nosniff', $r['headers']['X-Content-Type-Options']);
        $this->assertSame('DENY', $r['headers']['X-Frame-Options']);
    }

    public function testPasswordsAreBcryptAndNeverReturned(): void
    {
        $admin = $this->as('admin');
        $users = $admin->get('/api/v1/users');
        $this->assertNotContains('password_hash', self::allKeys($users['json']));
        $this->assertStringStartsWith('$2y$12$', (string) $this->value("SELECT password_hash FROM users WHERE username = 'pm1_demo'"));
    }
}
