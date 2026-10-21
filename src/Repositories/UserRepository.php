<?php

declare(strict_types=1);

namespace Lubie\Repositories;

use Lubie\Db\Database;

/** Users and roles. Password hashes are never returned by list methods. */
final class UserRepository
{
    private const PUBLIC_COLS = 'u.id, u.username, u.full_name, u.phone_e164, u.email, u.status, u.must_change_password, u.last_login_at,
        u.failed_login_count, u.locked_until, u.theme_pref, u.sms_opt_in, u.created_at, r.code AS role_code, r.name AS role_name, r.dashboard_path';

    public function __construct(private Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveWithRole(int $id): ?array
    {
        return $this->db->one('SELECT ' . self::PUBLIC_COLS . " FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = 'active'", [$id]);
    }

    /** @return array<string, mixed>|null includes password_hash (login only) */
    public function findForLogin(string $username): ?array
    {
        return $this->db->one('SELECT ' . self::PUBLIC_COLS . ', u.password_hash FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ?', [$username]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT ' . self::PUBLIC_COLS . ' FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id]);
    }

    public function passwordHash(int $id): ?string
    {
        $v = $this->db->value('SELECT password_hash FROM users WHERE id = ?', [$id]);
        return $v === null ? null : (string) $v;
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $role = null): array
    {
        $sql = 'SELECT ' . self::PUBLIC_COLS . ' FROM users u JOIN roles r ON r.id = u.role_id';
        return $role ? $this->db->all($sql . ' WHERE r.code = ? ORDER BY u.full_name', [$role]) : $this->db->all($sql . ' ORDER BY r.id, u.full_name');
    }

    public function roleId(string $code): ?int
    {
        $v = $this->db->value('SELECT id FROM roles WHERE code = ?', [$code]);
        return $v === null ? null : (int) $v;
    }

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        return $this->db->all('SELECT id, code, name FROM roles ORDER BY id');
    }

    /** @param array<string, mixed> $u */
    public function create(array $u, string $hash, ?int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO users (role_id, username, full_name, phone_e164, email, password_hash, must_change_password, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)',
            [$u['role_id'], $u['username'], $u['full_name'], $u['phone'] ?? null, $u['email'] ?? null, $hash, 'active', $createdBy]
        );
    }

    /** @param array<string, mixed> $fields column => value (whitelisted by caller) */
    public function update(int $id, array $fields): void
    {
        $this->db->updateById('users', $id, $fields, ['role_id', 'full_name', 'phone_e164', 'email', 'status', 'must_change_password', 'password_hash',
            'failed_login_count', 'locked_until', 'last_login_at', 'theme_pref', 'sms_opt_in']);
    }

    public function countAdmins(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin'");
    }

    public function recordAttempt(string $usernameHash, string $ip, bool $success, string $at): void
    {
        $this->db->run('INSERT INTO login_attempts (username_hash, ip_address, success, attempted_at) VALUES (?, ?, ?, ?)', [$usernameHash, $ip, $success ? 1 : 0, $at]);
    }

    public function failedAttemptsFromIp(string $ip, string $since): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at >= ?', [$ip, $since]);
    }
}
