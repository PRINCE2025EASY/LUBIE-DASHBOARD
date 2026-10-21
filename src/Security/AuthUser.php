<?php

declare(strict_types=1);

namespace Lubie\Security;

/** The authenticated user, loaded from trusted database records on every request. */
final class AuthUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $fullName,
        public readonly string $role,
        public readonly string $dashboardPath,
        public readonly bool $mustChangePassword,
        public readonly string $theme,
        public readonly ?string $phone,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['full_name'],
            (string) $row['role_code'],
            (string) $row['dashboard_path'],
            (bool) $row['must_change_password'],
            (string) $row['theme_pref'],
            $row['phone_e164'] !== null ? (string) $row['phone_e164'] : null,
        );
    }

    public function is(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
