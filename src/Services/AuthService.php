<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Http\HttpException;
use Lubie\Repositories\SettingsRepository;
use Lubie\Repositories\UserRepository;
use Lubie\Security\AuthUser;
use Lubie\Security\DbSessionHandler;
use Lubie\Support\Clock;

/**
 * Unified login (FR-01, FR-29): generic errors, constant-time verification for unknown
 * users, per-account lockout and per-IP throttling, password change and admin reset (FR-02).
 */
final class AuthService
{
    public const HASH_OPTIONS = ['cost' => 12];
    public const GENERIC_ERROR = 'Username or password is incorrect.';
    private const DUMMY_HASH = '$2y$12$oU/.etZSl.6sRx4n4fgenuGInNPJb.43to9t.kELc7hHfF.UPlm3S'; // hash of a random discarded value

    public function __construct(
        private UserRepository $users,
        private AuditLogger $audit,
        private SettingsRepository $settings,
        private Clock $clock,
        private DbSessionHandler $sessions,
    ) {
    }

    public function login(string $username, string $password, string $ip): AuthUser
    {
        $username = trim($username);
        $now = $this->clock->now();
        $uHash = hash('sha256', strtolower($username));
        $window = $now->modify('-15 minutes')->format('Y-m-d H:i:s');
        if ($this->users->failedAttemptsFromIp($ip, $window) >= 20) {
            $this->audit->log(null, 'login_throttled', 'ip', $ip, null, null, $username);
            throw HttpException::tooMany();
        }
        $row = $username === '' ? null : $this->users->findForLogin($username);
        if ($row === null) {
            password_verify($password, self::DUMMY_HASH);
            $this->fail(null, $username, $uHash, $ip);
        }
        $id = (int) $row['id'];
        if ($row['status'] === 'locked' && $row['locked_until'] !== null && (string) $row['locked_until'] <= $now->format('Y-m-d H:i:s')) {
            $this->users->update($id, ['status' => 'active', 'failed_login_count' => 0, 'locked_until' => null]);
            $row['status'] = 'active';
            $row['failed_login_count'] = 0;
        }
        $ok = password_verify($password, (string) $row['password_hash']);
        if ($row['status'] !== 'active') {
            $this->fail($id, $username, $uHash, $ip, 'account ' . $row['status']);
        }
        if (!$ok) {
            $fails = (int) $row['failed_login_count'] + 1;
            $limit = $this->settings->int('lockout_threshold', 5);
            $upd = ['failed_login_count' => $fails];
            if ($fails >= $limit) {
                $upd += ['status' => 'locked', 'locked_until' => $now->modify('+' . $this->settings->int('lockout_minutes', 15) . ' minutes')->format('Y-m-d H:i:s')];
                $this->audit->log($id, 'lockout', 'user', (string) $id);
            }
            $this->users->update($id, $upd);
            $this->fail($id, $username, $uHash, $ip);
        }
        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT, self::HASH_OPTIONS)) {
            $this->users->update($id, ['password_hash' => password_hash($password, PASSWORD_DEFAULT, self::HASH_OPTIONS)]);
        }
        $this->users->update($id, ['failed_login_count' => 0, 'last_login_at' => $now->format('Y-m-d H:i:s')]);
        $this->users->recordAttempt($uHash, $ip, true, $now->format('Y-m-d H:i:s'));
        $this->audit->log($id, 'login', 'user', (string) $id);
        return AuthUser::fromRow($this->users->findActiveWithRole($id) ?? $row);
    }

    private function fail(?int $userId, string $username, string $uHash, string $ip, string $note = 'bad credentials'): never
    {
        $this->users->recordAttempt($uHash, $ip, false, $this->clock->nowSql());
        $this->audit->log($userId, 'login_failed', 'user', $userId !== null ? (string) $userId : null, null, ['reason' => $note], $userId === null ? $username : null);
        throw new HttpException(401, 'LOGIN_FAILED', self::GENERIC_ERROR);
    }

    public function changePassword(AuthUser $u, string $current, string $new): void
    {
        $hash = $this->users->passwordHash($u->id);
        if ($hash === null || !password_verify($current, $hash)) {
            throw HttpException::validation(['current_password' => 'Your current password is not correct.']);
        }
        $this->assertStrong($new, $u->username);
        if (password_verify($new, $hash)) {
            throw HttpException::validation(['new_password' => 'Choose a password you have not used here before.']);
        }
        $this->users->update($u->id, ['password_hash' => password_hash($new, PASSWORD_DEFAULT, self::HASH_OPTIONS), 'must_change_password' => 0]);
        $this->audit->log($u->id, 'password_change', 'user', (string) $u->id);
    }

    public function adminReset(int $adminId, int $userId, string $temporary): void
    {
        $target = $this->users->find($userId) ?? throw HttpException::notFound('User not found.');
        $this->assertStrong($temporary, (string) $target['username']);
        $this->users->update($userId, ['password_hash' => password_hash($temporary, PASSWORD_DEFAULT, self::HASH_OPTIONS), 'must_change_password' => 1,
            'failed_login_count' => 0, 'locked_until' => null] + ($target['status'] === 'locked' ? ['status' => 'active'] : []));
        $this->sessions->destroyAllForUser($userId);
        $this->audit->log($adminId, 'password_reset', 'user', (string) $userId);
    }

    public function assertStrong(string $pw, string $username): void
    {
        $min = $this->settings->int('password_min_length', 10);
        $errors = [];
        if (mb_strlen($pw) < $min) {
            $errors[] = "at least {$min} characters";
        }
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
            $errors[] = 'letters and numbers';
        }
        if ($username !== '' && stripos($pw, $username) !== false) {
            $errors[] = 'not your username';
        }
        if ($errors) {
            throw HttpException::validation(['new_password' => 'Use ' . implode(', ', $errors) . '.']);
        }
    }
}
