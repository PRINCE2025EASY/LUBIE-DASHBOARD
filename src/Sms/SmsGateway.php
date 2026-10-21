<?php

declare(strict_types=1);

namespace Lubie\Sms;

/** Adapter interface for SMS providers (FR-22). Credentials come only from environment variables. */
interface SmsGateway
{
    public function name(): string;

    /** @return array{ok:bool, ref:?string, error:?string} */
    public function send(string $phoneE164, string $message): array;
}
