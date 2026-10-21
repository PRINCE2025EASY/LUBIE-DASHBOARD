<?php

declare(strict_types=1);

namespace Lubie\Security;

/** Synchroniser-token CSRF protection; token lives in the server-side session. */
final class Csrf
{
    public static function token(Session $s): string
    {
        $t = $s->get('csrf');
        if (!is_string($t) || strlen($t) !== 64) {
            $t = bin2hex(random_bytes(32));
            $s->set('csrf', $t);
        }
        return $t;
    }

    public static function valid(Session $s, string $given): bool
    {
        $t = $s->get('csrf');
        return is_string($t) && $given !== '' && hash_equals($t, $given);
    }

    public static function rotate(Session $s): string
    {
        $s->forget('csrf');
        return self::token($s);
    }
}
