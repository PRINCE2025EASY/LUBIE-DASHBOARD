<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Security\AuthUser;
use Lubie\Support\Clock;

/**
 * Offline synchronisation (FR-38). Each queued item carries a client_uuid. The server applies
 * the same validation and scope checks as a direct request; a replayed uuid returns the first
 * result instead of creating a duplicate.
 */
final class SyncService
{
    public const MAX_ITEMS = 50;

    public function __construct(private Database $db, private ProgressService $progress, private Clock $clock)
    {
    }

    /**
     * @param list<mixed> $items
     * @return list<array<string, mixed>>
     */
    public function process(AuthUser $u, array $items): array
    {
        if (count($items) > self::MAX_ITEMS) {
            throw HttpException::validation(['items' => 'Send at most ' . self::MAX_ITEMS . ' items per request.']);
        }
        $results = [];
        foreach ($items as $item) {
            $results[] = $this->one($u, is_array($item) ? $item : []);
        }
        return $results;
    }

    /** @param array<string, mixed> $item */
    private function one(AuthUser $u, array $item): array
    {
        $uuid = strtolower((string) ($item['client_uuid'] ?? ''));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)) {
            return ['client_uuid' => $uuid, 'status' => 422, 'error' => 'Missing or invalid client_uuid.'];
        }
        $prev = $this->db->one('SELECT user_id, result_code, entity_id FROM sync_requests WHERE client_uuid = ?', [$uuid]);
        if ($prev !== null) {
            if ((int) $prev['user_id'] !== $u->id) {
                return ['client_uuid' => $uuid, 'status' => 409, 'error' => 'This item was sent by another user.'];
            }
            return ['client_uuid' => $uuid, 'status' => (int) $prev['result_code'], 'entity_id' => $prev['entity_id'] !== null ? (int) $prev['entity_id'] : null, 'replayed' => true];
        }
        $type = (string) ($item['type'] ?? '');
        try {
            if ($type !== 'progress') {
                throw HttpException::validation(['type' => 'This item type cannot be sent offline.']);
            }
            $body = is_array($item['body'] ?? null) ? $item['body'] : [];
            $body['client_uuid'] = $uuid;
            $r = $this->progress->submit($u, (int) ($item['activity_id'] ?? 0), $body);
            $code = 201;
            $result = ['client_uuid' => $uuid, 'status' => 201, 'entity_id' => $r['id'], 'record_status' => $r['status'], 'replayed' => $r['replayed']];
            $entity = $r['id'];
        } catch (HttpException $e) {
            $code = $e->status;
            $result = ['client_uuid' => $uuid, 'status' => $e->status, 'error' => $e->getMessage(), 'fields' => $e->fields ?: null];
            $entity = null;
        }
        if ($code < 500) {
            $this->db->run('INSERT INTO sync_requests (client_uuid, user_id, endpoint, result_code, entity_type, entity_id, received_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$uuid, $u->id, $type ?: 'unknown', $code, $entity !== null ? 'progress' : null, $entity, $this->clock->nowSql()]);
        }
        return $result;
    }
}
