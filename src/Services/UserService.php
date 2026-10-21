<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Http\HttpException;
use Lubie\Repositories\UserRepository;
use Lubie\Security\AuthUser;
use Lubie\Security\DbSessionHandler;
use Lubie\Validation\Validator;

/** User administration (FR-05). No self-registration. */
final class UserService
{
    public function __construct(private UserRepository $users, private AuthService $auth, private AuditLogger $audit, private DbSessionHandler $sessions)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $role = null): array
    {
        return $this->users->list($role);
    }

    /** @param array<string, mixed> $in */
    public function create(AuthUser $admin, array $in): int
    {
        $roles = array_column($this->users->roles(), 'code');
        $v = Validator::of($in)->string('username', 50, true, 3)->string('full_name', 120, true, 2)->in('role', $roles)
            ->phone('phone')->string('email', 190, false)->string('temporary_password', 200);
        if (!preg_match('/^[a-z0-9._-]+$/', (string) ($in['username'] ?? ''))) {
            $v->addError('username', 'Use lower-case letters, numbers, dot, dash or underscore.');
        }
        $d = $v->validated();
        if ($this->users->findForLogin($d['username']) !== null) {
            throw HttpException::validation(['username' => 'This username is already taken.']);
        }
        try {
            $this->auth->assertStrong($d['temporary_password'], $d['username']);
        } catch (HttpException $e) {
            throw HttpException::validation(['temporary_password' => $e->fields['new_password'] ?? 'Choose a stronger password.']);
        }
        $id = $this->users->create(['role_id' => $this->users->roleId($d['role']), 'username' => $d['username'], 'full_name' => $d['full_name'],
            'phone' => $d['phone'], 'email' => $d['email']], password_hash($d['temporary_password'], PASSWORD_DEFAULT, AuthService::HASH_OPTIONS), $admin->id);
        $this->audit->log($admin->id, 'create', 'user', (string) $id, null, ['username' => $d['username'], 'role' => $d['role']]);
        return $id;
    }

    /** @param array<string, mixed> $in */
    public function update(AuthUser $admin, int $id, array $in): void
    {
        $old = $this->users->find($id) ?? throw HttpException::notFound('User not found.');
        $roles = array_column($this->users->roles(), 'code');
        $d = Validator::of($in + ['full_name' => $old['full_name'], 'role' => $old['role_code'], 'status' => $old['status']])
            ->string('full_name', 120, true, 2)->in('role', $roles)->in('status', ['active', 'deactivated'])
            ->phone('phone')->string('email', 190, false)->validated();
        if ($id === $admin->id && ($d['status'] !== 'active' || $d['role'] !== 'admin')) {
            throw HttpException::conflict('You cannot deactivate yourself or remove your own Administrator role.');
        }
        $fields = ['full_name' => $d['full_name'], 'role_id' => $this->users->roleId($d['role']), 'status' => $d['status']];
        if (array_key_exists('phone', $in)) {
            $fields['phone_e164'] = $d['phone'];
        }
        if (array_key_exists('email', $in)) {
            $fields['email'] = $d['email'];
        }
        $this->users->update($id, $fields);
        if ($d['status'] === 'deactivated' || $d['role'] !== $old['role_code']) {
            $this->sessions->destroyAllForUser($id);
        }
        $this->audit->logChange($admin->id, $d['role'] !== $old['role_code'] ? 'role_change' : 'update', 'user', $id,
            ['full_name' => $old['full_name'], 'role' => $old['role_code'], 'status' => $old['status']], ['full_name' => $d['full_name'], 'role' => $d['role'], 'status' => $d['status']]);
    }

    public function unlock(AuthUser $admin, int $id): void
    {
        $u = $this->users->find($id) ?? throw HttpException::notFound('User not found.');
        if ($u['status'] !== 'locked') {
            throw HttpException::conflict('This account is not locked.');
        }
        $this->users->update($id, ['status' => 'active', 'failed_login_count' => 0, 'locked_until' => null]);
        $this->audit->log($admin->id, 'unlock', 'user', (string) $id);
    }
}
