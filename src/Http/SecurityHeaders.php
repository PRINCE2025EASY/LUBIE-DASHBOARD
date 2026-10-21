<?php

declare(strict_types=1);

namespace Lubie\Http;

use Lubie\Support\Env;

/** Security headers on every response (NFR-01, ST-13). No inline scripts are used, so CSP is strict. */
final class SecurityHeaders
{
    public static function apply(Response $r, Request $req): Response
    {
        $r->headers += [
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; "
                . "font-src 'self'; connect-src 'self'; worker-src 'self'; manifest-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'X-Frame-Options' => 'DENY',
            'Permissions-Policy' => 'camera=(self), geolocation=(), microphone=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if (Env::bool('SESSION_SECURE_COOKIE', false)) {
            $r->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        return $r;
    }
}
