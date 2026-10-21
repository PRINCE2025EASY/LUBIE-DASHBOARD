<?php

declare(strict_types=1);

namespace Lubie\Sms;

/**
 * Log-only adapter for development and tests. Nothing is sent to a phone; the sms_log
 * row records provider 'sandbox' so reports never claim real delivery.
 */
final class SandboxSmsGateway implements SmsGateway
{
    /** @var list<array{phone:string, message:string}> */
    public array $sent = [];

    public function name(): string
    {
        return 'sandbox';
    }

    public function send(string $phoneE164, string $message): array
    {
        $this->sent[] = ['phone' => $phoneE164, 'message' => $message];
        return ['ok' => true, 'ref' => 'sandbox-' . bin2hex(random_bytes(4)), 'error' => null];
    }
}
