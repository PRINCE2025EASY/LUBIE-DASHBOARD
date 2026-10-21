<?php

declare(strict_types=1);

namespace Lubie\Alerts;

/** A rule result that is currently true. The dedup key identifies it while it stays open. */
final class Condition
{
    /**
     * @param list<string> $recipients role codes that receive the in-app alert
     * @param list<string> $smsTo role codes that also receive SMS (never 'client')
     */
    public function __construct(
        public readonly string $rule,
        public readonly string $dedupKey,
        public readonly string $severity,
        public readonly string $entityType,
        public readonly ?int $entityId,
        public readonly string $title,
        public readonly string $description,
        public readonly array $recipients,
        public readonly array $smsTo = [],
        public readonly bool $clientVisible = false,
    ) {
    }
}
