<?php

declare(strict_types=1);

namespace Lubie\Sms;

use Lubie\Support\Env;

/**
 * Arkesel-compatible HTTP adapter (POST JSON, api-key header, 5 s timeout). Base URL,
 * key and sender ID are read from the environment. Untested until a real account is
 * configured; the system never reports delivery without the provider's acceptance.
 */
final class HttpSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return 'arkesel';
    }

    public function send(string $phoneE164, string $message): array
    {
        $url = Env::get('SMS_BASE_URL', 'https://sms.arkesel.com/api/v2/sms/send');
        $key = Env::get('SMS_API_KEY');
        $sender = Env::get('SMS_SENDER_ID', 'Lubie');
        if (!$key || !function_exists('curl_init')) {
            return ['ok' => false, 'ref' => null, 'error' => 'SMS provider not configured'];
        }
        $ch = curl_init((string) $url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['api-key: ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['sender' => $sender, 'message' => $message, 'recipients' => [ltrim($phoneE164, '+')]]),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            return ['ok' => false, 'ref' => null, 'error' => mb_substr($err ?: "HTTP {$code}", 0, 300)];
        }
        $json = json_decode((string) $body, true);
        $ref = is_array($json) ? (string) ($json['data'][0]['id'] ?? $json['id'] ?? 'accepted') : 'accepted';
        return ['ok' => true, 'ref' => mb_substr($ref, 0, 80), 'error' => null];
    }
}
