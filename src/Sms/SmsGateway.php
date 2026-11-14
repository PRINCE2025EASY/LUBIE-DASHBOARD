<?php

declare(strict_types=1);

namespace Lubie\Sms;

interface SmsGateway
{
    public function name(): string;

    /** @return array{ok:bool, ref:?string, error:?string} */
    public function send(string $phoneE164, string $message): array;
}
