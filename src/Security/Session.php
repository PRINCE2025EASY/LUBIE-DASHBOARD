<?php

declare(strict_types=1);

namespace Lubie\Security;

use Lubie\Support\Clock;
use Lubie\Support\Env;

/**
 * Per-request session backed by DbSessionHandler. Data is JSON (never unserialize()).
 * Enforces idle timeout and absolute lifetime; supports ID regeneration at login.
 */
final class Session
{
    private string $id = '';
    /** @var array<string, mixed> */
    private array $data = [];
    private bool $dirty = false;
    private bool $destroyed = false;
    private ?string $createdAt = null;
    private bool $expired = false;

    public function __construct(private DbSessionHandler $handler, private Clock $clock, private int $idleMinutes = 20, private int $absoluteHours = 12)
    {
    }

    public static function cookieName(): string
    {
        return Env::bool('SESSION_SECURE_COOKIE', false) ? '__Host-lubie' : 'lubie_sid';
    }

    public function start(?string $cookieId): void
    {
        if ($cookieId !== null && preg_match('/^[a-f0-9]{64}$/', $cookieId)) {
            $row = $this->handler->readRow($cookieId);
            if ($row !== null) {
                $now = $this->clock->now()->getTimestamp();
                $idle = $now - strtotime($row['last_activity'] . ' UTC');
                $age = $now - strtotime($row['created_at'] . ' UTC');
                if ($idle <= $this->idleMinutes * 60 && $age <= $this->absoluteHours * 3600) {
                    $this->id = $cookieId;
                    $decoded = json_decode($row['data'], true);
                    $this->data = is_array($decoded) ? $decoded : [];
                    $this->createdAt = $row['created_at'];
                    $this->dirty = true; // refresh last_activity
                    return;
                }
                $this->expired = $row['user_id'] !== null;
                $this->handler->destroy($cookieId);
            }
        }
        $this->id = self::newId();
        $this->data = [];
        $this->dirty = false;
    }

    public static function newId(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function id(): string
    {
        return $this->id;
    }

    /** True when the previous logged-in session timed out (shown as a message on the login page). */
    public function wasExpired(): bool
    {
        return $this->expired;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->dirty = true;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
        $this->dirty = true;
    }

    /** New ID, same data (session fixation protection at login and privilege change). */
    public function regenerate(): void
    {
        if ($this->id !== '') {
            $this->handler->destroy($this->id);
        }
        $this->id = self::newId();
        $this->dirty = true;
    }

    public function destroy(): void
    {
        if ($this->id !== '') {
            $this->handler->destroy($this->id);
        }
        $this->data = [];
        $this->destroyed = true;
    }

    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    public function isDirty(): bool
    {
        return $this->dirty && !$this->destroyed;
    }

    public function save(?int $userId, string $ip, string $agent): void
    {
        if (!$this->isDirty()) {
            return;
        }
        $this->handler->setContext($userId, $ip, $agent);
        $this->handler->write($this->id, (string) json_encode($this->data));
        if (random_int(1, 100) === 1) {
            $this->handler->gc($this->absoluteHours * 3600);
        }
    }

    public function cookieHeader(string $basePath): string
    {
        $secure = Env::bool('SESSION_SECURE_COOKIE', false);
        $name = self::cookieName();
        $path = $secure ? '/' : ($basePath === '' ? '/' : $basePath);
        if ($this->destroyed) {
            return sprintf('%s=deleted; Path=%s; Max-Age=0; HttpOnly; SameSite=Lax%s', $name, $path, $secure ? '; Secure' : '');
        }
        return sprintf('%s=%s; Path=%s; HttpOnly; SameSite=Lax%s', $name, $this->id, $path, $secure ? '; Secure' : '');
    }
}
