<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Repositories\ProjectRepository;
use Lubie\Repositories\ResourceRepository;
use Lubie\Repositories\ScheduleRepository;
use Lubie\Security\AuthUser;
use Lubie\Security\Authorizer;
use Lubie\Support\Clock;
use Lubie\Support\Money;
use Lubie\Validation\Validator;

/**
 * Labour, materials and equipment (FR-18, FR-19), material requests and store balance (FR-33),
 * with the reorder (A10) and faulty-equipment (A14) alerts evaluated after every change.
 * Balance = received - used. A use that would take the balance below zero needs confirmation.
 */
final class ResourceService
{
    public const UNITS = ['bag', 't', 'm3', 'm2', 'm', 'pcs', 'kg', 'litre', 'trip', 'hr', 'persons'];
    public const EQUIPMENT_STATUSES = ['working', 'faulty', 'idle', 'off_site'];
    private const MOVEMENTS = ['material' => ['received', 'used'], 'labour' => ['attendance'], 'equipment' => ['hours']];

    public function __construct(
        private Database $db,
        private ResourceRepository $repo,
        private ProjectRepository $projects,
        private ScheduleRepository $schedule,
        private Authorizer $authz,
        private AuditLogger $audit,
        private ChangeTracker $changes,
        private AlertService $alerts,
        private Clock $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function overview(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId);
        $p = $this->projects->find($projectId);
        $items = array_map(fn (array $r) => Presenter::resource($r, $u->role), $this->repo->forProject($projectId, $this->clock->today()));
        $by = ['material' => [], 'labour' => [], 'equipment' => []];
        foreach ($items as $i) {
            $by[$i['category']][] = $i;
        }
        $requests = array_map(static fn (array $m) => [
            'id' => (int) $m['id'], 'resource_id' => (int) $m['resource_id'], 'resource_name' => $m['resource_name'], 'unit' => $m['unit'],
            'quantity' => (float) $m['quantity'], 'required_by' => $m['required_by'], 'status' => $m['status'], 'remarks' => $m['remarks'],
            'requested_by' => (int) $m['requested_by'], 'requested_by_name' => $m['requested_by_name'], 'requested_at' => $m['requested_at'],
            'decided_by_name' => $m['decided_by_name'], 'decided_at' => $m['decided_at'],
        ], $this->repo->requests($projectId));
        return [
            'project' => ['id' => $projectId, 'code' => $p['code'], 'name' => $p['name'], 'status' => $p['status'], 'is_demo' => (bool) $p['is_demo']],
            'materials' => $by['material'], 'labour' => $by['labour'], 'equipment' => $by['equipment'],
            'requests' => $requests,
            'recent' => array_map(fn (array $m) => $this->presentMovement($m), $this->repo->recentUsage($projectId, 30)),
            'summary' => [
                'low_stock' => count(array_filter($by['material'], static fn ($m) => $m['low'])),
                'faulty_equipment' => count(array_filter($by['equipment'], static fn ($e) => $e['equipment_status'] === 'faulty')),
                'workers_today' => array_sum(array_column($by['labour'], 'today_qty')),
                'open_requests' => count(array_filter($requests, static fn ($m) => in_array($m['status'], ['requested', 'approved'], true))),
            ],
            'can' => [
                'record' => $this->authz->can($u, 'resources.record'),
                'rates' => $this->authz->can($u, 'resources.cost'),
                'decide' => $this->authz->can($u, 'requests.decide'),
            ],
            'open' => $this->isOpen($p),
            'today' => $this->clock->today(),
            'last_updated' => $p['last_change_at'],
            'change_seq' => (int) $p['change_seq'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function history(AuthUser $u, int $resourceId): array
    {
        $r = $this->repo->find($resourceId) ?? throw HttpException::notFound();
        $this->authz->requireProject($u, (int) $r['project_id']);
        return array_map(fn (array $m) => $this->presentMovement($m), $this->repo->recentUsage((int) $r['project_id'], 100, $resourceId));
    }

    /** @param array<string, mixed> $in */
    public function create(AuthUser $u, int $projectId, array $in): int
    {
        $this->authz->requireProject($u, $projectId);
        $this->assertOpen($projectId);
        $v = Validator::of($in)->in('category', array_keys(self::MOVEMENTS))->string('name', 120, true, 2)
            ->in('unit', self::UNITS, false)->quantity('planned_qty', false)->quantity('reorder_level', false)
            ->money('unit_rate', false)->in('equipment_status', self::EQUIPMENT_STATUSES, false);
        $d = $v->validated();
        $d['unit'] = match ($d['category']) {
            'labour' => 'persons',
            'equipment' => 'hr',
            default => $d['unit'] ?? throw HttpException::validation(['unit' => 'Choose the unit.']),
        };
        if ($d['category'] === 'material' && in_array($d['unit'], ['persons', 'hr'], true)) {
            throw HttpException::validation(['unit' => 'Choose a material unit such as bag, t or pcs.']);
        }
        if ($d['category'] !== 'material') {
            $d['reorder_level'] = null;
        }
        $d['equipment_status'] = $d['category'] === 'equipment' ? ($d['equipment_status'] ?? 'working') : null;
        if ($d['unit_rate'] !== null && !$this->authz->can($u, 'resources.cost')) {
            $d['unit_rate'] = null; // rates are set by the Project Manager only; ignored for others
        }
        $d['unit_rate'] = $d['unit_rate'] === null ? null : Money::toString($d['unit_rate']);
        $catId = $this->repo->categoryId($d['category']) ?? throw HttpException::validation(['category' => 'Unknown category.']);
        if ($this->repo->nameExists($projectId, $catId, $d['name'])) {
            throw HttpException::conflict('An item with this name already exists on the project.');
        }
        $id = $this->db->transaction(function () use ($u, $projectId, $catId, $d) {
            $id = $this->repo->insert($projectId, $catId, $d, $u->id);
            $this->audit->log($u->id, 'create', 'resource', (string) $id, null, array_diff_key($d, ['unit_rate' => 1]) + ['rate_set' => $d['unit_rate'] !== null]);
            $this->changes->touch($projectId);
            return $id;
        });
        $this->alerts->evaluate($projectId);
        return $id;
    }

    /** @param array<string, mixed> $in */
    public function update(AuthUser $u, int $resourceId, array $in): void
    {
        $r = $this->repo->find($resourceId) ?? throw HttpException::notFound();
        $pid = (int) $r['project_id'];
        $this->authz->requireProject($u, $pid);
        $this->assertOpen($pid);
        $pm = $this->authz->can($u, 'resources.cost');
        $v = Validator::of($in);
        $fields = [];
        if (array_key_exists('name', $in)) {
            $fields['name'] = $v->string('name', 120, true, 2)->validated()['name'];
            if ($this->repo->nameExists($pid, (int) $r['category_id'], $fields['name'], $resourceId)) {
                throw HttpException::conflict('An item with this name already exists on the project.');
            }
        }
        if (array_key_exists('planned_qty', $in)) {
            $fields['planned_qty'] = $v->quantity('planned_qty', false)->validated()['planned_qty'];
        }
        if (array_key_exists('reorder_level', $in) && $r['category'] === 'material') {
            $fields['reorder_level'] = $v->quantity('reorder_level', false)->validated()['reorder_level'];
        }
        if ($pm && array_key_exists('unit_rate', $in)) {
            $rate = $v->money('unit_rate', false)->validated()['unit_rate'];
            $fields['unit_rate'] = $rate === null ? null : Money::toString($rate);
        }
        if ($pm && array_key_exists('status', $in)) {
            $fields['status'] = $v->in('status', ['active', 'archived'])->validated()['status'];
        }
        if (!$fields) {
            throw HttpException::validation(['name' => 'Nothing to change.']);
        }
        $old = array_intersect_key($r, $fields);
        $this->db->transaction(function () use ($u, $resourceId, $pid, $fields, $old) {
            $this->repo->update($resourceId, $fields);
            $this->audit->log($u->id, 'update', 'resource', (string) $resourceId, $old, $fields);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    /**
     * Record a movement: materials received or used, labour attendance, equipment hours.
     * @param array<string, mixed> $in
     * @return array{id:int, replayed:bool, balance:?float}
     */
    public function recordUsage(AuthUser $u, int $resourceId, array $in): array
    {
        $r = $this->repo->find($resourceId) ?? throw HttpException::notFound('Item not found.');
        $pid = (int) $r['project_id'];
        $this->authz->requireProject($u, $pid);
        $d = Validator::of($in)->date('usage_date', true, $this->clock->today())->in('movement', self::MOVEMENTS[$r['category']] ?? [])
            ->quantity('quantity', true, true)->int('activity_id', 1, PHP_INT_MAX, false)->string('remarks', 300, false)
            ->uuid('client_uuid')->validated();
        if ($d['client_uuid'] !== null && ($prev = $this->repo->usageByClientUuid($d['client_uuid'])) !== null) {
            return ['id' => (int) $prev['id'], 'replayed' => true, 'balance' => $r['category'] === 'material' ? $this->repo->balance($resourceId) : null];
        }
        if ($r['status'] !== 'active') {
            throw HttpException::conflict('This item is archived.');
        }
        $this->assertOpen($pid);
        if ($d['activity_id'] !== null) {
            $a = $this->schedule->activity($d['activity_id']);
            if ($a === null || (int) $a['project_id'] !== $pid) {
                throw HttpException::validation(['activity_id' => 'Choose an activity of this project.']);
            }
        }
        if ($r['category'] === 'labour' && (float) $d['quantity'] !== floor((float) $d['quantity'])) {
            throw HttpException::validation(['quantity' => 'Enter the number of workers as a whole number.']);
        }
        if ($r['category'] === 'equipment' && (float) $d['quantity'] > 24) {
            throw HttpException::validation(['quantity' => 'Enter at most 24 hours for one day.']);
        }
        $id = $this->db->transaction(function () use ($u, $r, $resourceId, $pid, $d, $in) {
            if ($d['movement'] === 'used') {
                $after = $this->repo->balance($resourceId) - (float) $d['quantity'];
                if ($after < 0 && !filter_var($in['confirm_negative'] ?? false, FILTER_VALIDATE_BOOL)) {
                    throw new HttpException(409, 'NEGATIVE_BALANCE', sprintf('This use is more than the %s %s in store. Check the quantity, or confirm to record it anyway.',
                        rtrim(rtrim(number_format($after + (float) $d['quantity'], 2, '.', ''), '0'), '.'), $r['unit']));
                }
            }
            $id = $this->repo->insertUsage($resourceId, $d, $u->id);
            $this->audit->log($u->id, 'create', 'resource_usage', (string) $id, null,
                ['resource_id' => $resourceId, 'movement' => $d['movement'], 'quantity' => $d['quantity'], 'date' => $d['usage_date']]);
            $this->changes->touch($pid);
            return $id;
        });
        $this->alerts->evaluate($pid);
        return ['id' => $id, 'replayed' => false, 'balance' => $r['category'] === 'material' ? $this->repo->balance($resourceId) : null];
    }

    /** @param array<string, mixed> $in */
    public function setEquipmentStatus(AuthUser $u, int $resourceId, array $in): void
    {
        $r = $this->repo->find($resourceId) ?? throw HttpException::notFound('Item not found.');
        $pid = (int) $r['project_id'];
        $this->authz->requireProject($u, $pid);
        if ($r['category'] !== 'equipment') {
            throw HttpException::validation(['equipment_status' => 'Only equipment has a status.']);
        }
        $this->assertOpen($pid);
        $d = Validator::of($in)->in('equipment_status', self::EQUIPMENT_STATUSES)->string('remarks', 300, false)->validated();
        if ($d['equipment_status'] === $r['equipment_status']) {
            return;
        }
        $this->db->transaction(function () use ($u, $resourceId, $pid, $d, $r) {
            $this->repo->update($resourceId, ['equipment_status' => $d['equipment_status']]);
            $this->audit->log($u->id, 'update', 'equipment_status', (string) $resourceId,
                ['equipment_status' => $r['equipment_status']], ['equipment_status' => $d['equipment_status'], 'remarks' => $d['remarks']]);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    /** @param array<string, mixed> $in */
    public function createRequest(AuthUser $u, int $projectId, array $in): int
    {
        $this->authz->requireProject($u, $projectId);
        $this->assertOpen($projectId);
        $d = Validator::of($in)->int('resource_id', 1, PHP_INT_MAX)->quantity('quantity', true, true)
            ->date('required_by')->string('remarks', 300, false)->validated();
        $r = $this->repo->find($d['resource_id']);
        if ($r === null || (int) $r['project_id'] !== $projectId || $r['category'] !== 'material' || $r['status'] !== 'active') {
            throw HttpException::validation(['resource_id' => 'Choose a material of this project.']);
        }
        if ($d['required_by'] < $this->clock->today()) {
            throw HttpException::validation(['required_by' => 'The date needed cannot be in the past.']);
        }
        $id = $this->db->transaction(function () use ($u, $projectId, $d) {
            $id = $this->repo->insertRequest($projectId, $d, $u->id, $this->clock->nowSql());
            $this->audit->log($u->id, 'create', 'material_request', (string) $id, null, $d);
            $this->changes->touch($projectId);
            return $id;
        });
        return $id;
    }

    /** PM approves a request. The requester cannot approve their own request (separation of duties). */
    public function approveRequest(AuthUser $u, int $id): void
    {
        $m = $this->repo->request($id) ?? throw HttpException::notFound();
        $pid = (int) $m['project_id'];
        $this->authz->requireProject($u, $pid);
        if ((int) $m['requested_by'] === $u->id) {
            throw HttpException::conflict('You cannot approve a request you made (separation of duties).');
        }
        $this->transition($u, $m, 'requested', 'approved', true, 'approve');
    }

    /** Cancel: the requester while it waits, or the PM at any open stage. A reason is required. */
    public function cancelRequest(AuthUser $u, int $id, ?string $reason): void
    {
        $m = $this->repo->request($id) ?? throw HttpException::notFound();
        $this->authz->requireProject($u, (int) $m['project_id']);
        $isPm = $this->authz->can($u, 'requests.decide');
        $mine = (int) $m['requested_by'] === $u->id;
        if (!$isPm && !($mine && $m['status'] === 'requested')) {
            throw HttpException::forbidden('Only the Project Manager, or the requester before approval, can cancel this request.');
        }
        $reason = trim((string) $reason);
        if (mb_strlen($reason) < 3) {
            throw HttpException::validation(['reason' => 'Give the reason for cancelling.']);
        }
        if (!in_array($m['status'], ['requested', 'approved'], true)) {
            throw HttpException::conflict('This request is already closed.');
        }
        $this->transition($u, $m, (string) $m['status'], 'cancelled', $isPm, 'cancel', mb_substr($reason, 0, 300));
    }

    /**
     * Mark an approved request delivered and add the delivered quantity to the store in one step.
     * @param array<string, mixed> $in
     */
    public function deliverRequest(AuthUser $u, int $id, array $in): void
    {
        $m = $this->repo->request($id) ?? throw HttpException::notFound();
        $pid = (int) $m['project_id'];
        $this->authz->requireProject($u, $pid);
        $this->assertOpen($pid);
        $d = Validator::of($in + ['quantity' => (string) $m['quantity'], 'usage_date' => $this->clock->today()])
            ->quantity('quantity', true, true)->date('usage_date', true, $this->clock->today())->string('remarks', 300, false)->validated();
        $this->db->transaction(function () use ($u, $m, $id, $pid, $d) {
            if ($this->repo->setRequestStatus($id, 'approved', 'delivered', null, null, false) !== 1) {
                throw HttpException::conflict('Only an approved request can be marked delivered.');
            }
            $this->repo->insertUsage((int) $m['resource_id'], ['activity_id' => null, 'usage_date' => $d['usage_date'], 'movement' => 'received',
                'quantity' => $d['quantity'], 'remarks' => trim('Request #' . $id . ' delivered. ' . ($d['remarks'] ?? '')), 'client_uuid' => null], $u->id);
            $this->audit->log($u->id, 'deliver', 'material_request', (string) $id, ['status' => 'approved'], ['status' => 'delivered', 'quantity' => $d['quantity']]);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    /** @param array<string, mixed> $m */
    private function transition(AuthUser $u, array $m, string $from, string $to, bool $recordDecider, string $action, ?string $note = null): void
    {
        $pid = (int) $m['project_id'];
        $this->db->transaction(function () use ($u, $m, $from, $to, $recordDecider, $action, $note, $pid) {
            if ($this->repo->setRequestStatus((int) $m['id'], $from, $to, $u->id, $this->clock->nowSql(), $recordDecider) !== 1) {
                throw HttpException::conflict($from === 'requested' ? 'This request is no longer waiting for a decision.' : 'This request changed; reload and try again.');
            }
            $this->audit->log($u->id, $action, 'material_request', (string) $m['id'], ['status' => $from], ['status' => $to] + ($note ? ['reason' => $note] : []));
            $this->changes->touch($pid);
        });
    }

    /** @param array<string, mixed> $m */
    private function presentMovement(array $m): array
    {
        return [
            'id' => (int) $m['id'], 'resource_id' => (int) $m['resource_id'], 'resource_name' => $m['resource_name'], 'unit' => $m['unit'],
            'category' => $m['category'], 'usage_date' => $m['usage_date'], 'movement' => $m['movement'], 'quantity' => (float) $m['quantity'],
            'remarks' => $m['remarks'], 'activity_code' => $m['activity_code'], 'recorded_by_name' => $m['recorded_by_name'],
        ];
    }

    /** @param array<string, mixed>|null $p */
    private function isOpen(?array $p): bool
    {
        return $p !== null && in_array($p['status'], ['planning', 'active', 'on_hold'], true);
    }

    private function assertOpen(int $projectId): void
    {
        if (!$this->isOpen($this->projects->find($projectId))) {
            throw HttpException::conflict('This project is closed for resource entries.');
        }
    }
}
