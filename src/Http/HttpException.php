<?php

declare(strict_types=1);

namespace Lubie\Http;

use RuntimeException;

/** An error with an HTTP status and a stable code, rendered in the single JSON error format. */
class HttpException extends RuntimeException
{
    /** @param array<string, string> $fields */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function badRequest(string $msg = 'The request is not valid.'): self
    {
        return new self(400, 'BAD_REQUEST', $msg);
    }

    public static function unauthenticated(): self
    {
        return new self(401, 'UNAUTHENTICATED', 'Please log in again.');
    }

    public static function forbidden(string $msg = 'You do not have permission for this action.'): self
    {
        return new self(403, 'FORBIDDEN', $msg);
    }

    public static function notFound(string $msg = 'Not found.'): self
    {
        return new self(404, 'NOT_FOUND', $msg);
    }

    public static function conflict(string $msg): self
    {
        return new self(409, 'CONFLICT', $msg);
    }

    /** @param array<string, string> $fields */
    public static function validation(array $fields, string $msg = 'Some fields need attention.'): self
    {
        return new self(422, 'VALIDATION_FAILED', $msg, $fields);
    }

    public static function tooMany(string $msg = 'Too many attempts. Please wait and try again.'): self
    {
        return new self(429, 'RATE_LIMITED', $msg);
    }
}
