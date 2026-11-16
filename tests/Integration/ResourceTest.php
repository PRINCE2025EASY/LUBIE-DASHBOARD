<?php

declare(strict_types=1);

namespace Lubie\Tests\Integration;

use Lubie\Tests\Support\IntegrationTestCase;

/**
 * IT-RES: labour, materials, equipment and material requests (FR-18, FR-19, FR-33, FR-34; task T8).
 * Demo data: project 1 has cement (id 3, balance 120, reorder 200), chippings (id 4), labour (1, 2),
 * excavator (id 5, faulty) and roller (id 6). se1 and pm1 are assigned to project 1; se2 to project 2.
 */
final class ResourceTest extends IntegrationTestCase
{
    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private static function ok(array $r): bool
    {
        return in_array($r['status'], [200, 201, 204], true);
    }

    private function item(array $d, int $id): array
    {
        foreach (array_merge($d['materials'], $d['labour'], $d['equipment']) as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        $this->fail("Resource {$id} not in response");
    }

    private function openAlert(string $rule, int $resourceId): bool
    {
        return (bool) $this->value('SELECT 1 FROM notifications WHERE dedup_key = ? AND cleared_at IS NULL', ["{$rule}:resource:{$resourceId}"]);
    }

    public function testSiteEngineerSeesQuantitiesButNoMoney(): void
    {
        $r = $this->as('se1')->get('/api/v1/projects/1/resources');
        $this->assertSame(200, $r['status'], $r['body']);
        $keys = self::allKeys($r['json']['data']);
        foreach (['unit_rate', 'value_used', 'value_received'] as $hidden) {
            $this->assertNotContains($hidden, $keys, "Site Engineer must not receive {$hidden}");
        }
        $cement = $this->item($r['json']['data'], 3);
        $this->assertEqualsWithDelta(120.0, $cement['balance'], 0.001, 'Balance = received - used');
        $this->assertTrue($cement['low'], 'Cement is below its reorder level');
        $this->assertTrue($r['json']['data']['can']['record']);
        $this->assertFalse($r['json']['data']['can']['rates']);
    }

    public function testMoneyRolesSeeRatesAndValues(): void
    {
        foreach (['pm1', 'qs1', 'director'] as $u) {
            $d = $this->as($u)->get('/api/v1/projects/1/resources')['json']['data'];
            $cement = $this->item($d, 3);
            $this->assertSame('95.00', $cement['unit_rate'], $u);
            $this->assertSame('131100.00', $cement['value_used'], "{$u}: 1,380 bags x GH₵ 95.00");
        }
        $qs = $this->as('qs1')->get('/api/v1/projects/1/resources')['json']['data'];
        $this->assertFalse($qs['can']['record'], 'QS views resources only');
    }

    public function testClientAndUnassignedUsersAreRefused(): void
    {
        $this->assertSame(403, $this->as('client1')->get('/api/v1/projects/1/resources')['status'], 'Clients have no resource permission');
        $se2 = $this->as('se2');
        $this->assertSame(404, $se2->get('/api/v1/projects/1/resources')['status'], 'Unassigned project is not found');
        $this->assertSame(404, $se2->post('/api/v1/resources/3/usage', ['usage_date' => date('Y-m-d'), 'movement' => 'used', 'quantity' => 1])['status']);
        $this->assertSame(403, $this->as('qs1')->post('/api/v1/resources/3/usage', ['usage_date' => date('Y-m-d'), 'movement' => 'received', 'quantity' => 1])['status'], 'QS cannot record');
    }

    public function testUsageValidationAndNegativeBalanceWarning(): void
    {
        $se = $this->as('se1');
        $today = date('Y-m-d');
        $this->assertSame(422, $se->post('/api/v1/resources/3/usage', ['usage_date' => $today, 'movement' => 'attendance', 'quantity' => 5])['status'], 'Movement must match the category');
        $this->assertSame(422, $se->post('/api/v1/resources/3/usage', ['usage_date' => date('Y-m-d', strtotime('+1 day')), 'movement' => 'used', 'quantity' => 5])['status'], 'No future dates');
        $this->assertSame(422, $se->post('/api/v1/resources/3/usage', ['usage_date' => $today, 'movement' => 'used', 'quantity' => 0])['status'], 'Quantity above zero');
        $this->assertSame(422, $se->post('/api/v1/resources/1/usage', ['usage_date' => $today, 'movement' => 'attendance', 'quantity' => '2.5'])['status'], 'Whole workers only');
        $this->assertSame(422, $se->post('/api/v1/resources/6/usage', ['usage_date' => $today, 'movement' => 'hours', 'quantity' => 25])['status'], 'At most 24 hours a day');
        $this->assertSame(422, $se->post('/api/v1/resources/3/usage', ['usage_date' => $today, 'movement' => 'used', 'quantity' => 5, 'activity_id' => 7])['status'], 'Activity of another project');

        $over = $se->post('/api/v1/resources/4/usage', ['usage_date' => $today, 'movement' => 'used', 'quantity' => 1000]);
        $this->assertSame(409, $over['status'], 'Using more than the balance needs confirmation');
        $this->assertSame('NEGATIVE_BALANCE', $over['json']['error']['code']);
        $ok = $se->post('/api/v1/resources/4/usage', ['usage_date' => $today, 'movement' => 'used', 'quantity' => 10, 'activity_id' => 4]);
        $this->assertSame(201, $ok['status'], $ok['body']);
        $this->assertEqualsWithDelta(140.0, $ok['json']['data']['balance'], 0.001);
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'resource_usage' AND entity_id = ?", [(string) $ok['json']['data']['id']]));
        $att = $se->post('/api/v1/resources/1/usage', ['usage_date' => $today, 'movement' => 'attendance', 'quantity' => 22]);
        $this->assertSame(201, $att['status'], $att['body']);
        $d = $se->get('/api/v1/projects/1/resources')['json']['data'];
        $this->assertEqualsWithDelta(22.0, $this->item($d, 1)['today_qty'], 0.001);
    }

    public function testReorderAlertRaisesAndClearsFromRealStock(): void
    {
        $se = $this->as('se1');
        // Make sure the alert is evaluated for the current stock, then receive enough to clear it.
        $se->post('/api/v1/resources/3/usage', ['usage_date' => date('Y-m-d'), 'movement' => 'used', 'quantity' => 1]);
        $this->assertTrue($this->openAlert('A10', 3), 'A10 open while cement is below reorder level');
        $r = $se->post('/api/v1/resources/3/usage', ['usage_date' => date('Y-m-d'), 'movement' => 'received', 'quantity' => 500, 'remarks' => 'DN 101']);
        $this->assertSame(201, $r['status'], $r['body']);
        $this->assertFalse($this->openAlert('A10', 3), 'A10 clears when the balance is back above the reorder level');
    }

    public function testEquipmentStatusRaisesAndClearsA14(): void
    {
        $se = $this->as('se1');
        $this->assertSame(422, $se->post('/api/v1/resources/6/equipment-status', ['equipment_status' => 'broken'])['status']);
        $this->assertSame(422, $se->post('/api/v1/resources/3/equipment-status', ['equipment_status' => 'faulty'])['status'], 'Only equipment has a status');
        $this->assertTrue(self::ok($se->post('/api/v1/resources/6/equipment-status', ['equipment_status' => 'faulty', 'remarks' => 'tyre burst'])));
        $this->assertTrue($this->openAlert('A14', 6));
        $this->assertTrue(self::ok($se->post('/api/v1/resources/6/equipment-status', ['equipment_status' => 'working'])));
        $this->assertFalse($this->openAlert('A14', 6), 'A14 clears when the equipment works again');
    }

    public function testRatesOnlyFromTheProjectManager(): void
    {
        $se = $this->as('se1');
        $r = $se->post('/api/v1/projects/1/resources', ['category' => 'material', 'name' => 'Sharp sand', 'unit' => 'm3', 'reorder_level' => '10', 'unit_rate' => '999']);
        $this->assertSame(201, $r['status'], $r['body']);
        $id = $r['json']['data']['id'];
        $this->assertNull($this->value('SELECT unit_rate FROM resources WHERE id = ?', [$id]), 'Rate sent by a Site Engineer is ignored');
        $this->assertSame(409, $se->post('/api/v1/projects/1/resources', ['category' => 'material', 'name' => 'Sharp sand', 'unit' => 'm3'])['status'], 'Duplicate name');
        $this->assertSame(422, $se->post('/api/v1/projects/1/resources', ['category' => 'material', 'name' => 'Odd unit', 'unit' => 'persons'])['status']);
        $lab = $se->post('/api/v1/projects/1/resources', ['category' => 'labour', 'name' => 'Steel benders', 'unit' => 'bag']);
        $this->assertSame('persons', $this->value('SELECT unit FROM resources WHERE id = ?', [$lab['json']['data']['id']]), 'Labour is always counted in persons');
        $pm = $this->as('pm1');
        $this->assertTrue(self::ok($pm->call('PATCH', "/api/v1/resources/{$id}", ['unit_rate' => '180.00'])));
        $this->assertSame('180.00', (string) $this->value('SELECT unit_rate FROM resources WHERE id = ?', [$id]));
        $se->call('PATCH', "/api/v1/resources/{$id}", ['unit_rate' => '1.00', 'status' => 'archived', 'reorder_level' => '12']);
        $this->assertSame('180.00', (string) $this->value('SELECT unit_rate FROM resources WHERE id = ?', [$id]), 'Site Engineer cannot change a rate');
        $this->assertSame('active', $this->value('SELECT status FROM resources WHERE id = ?', [$id]), 'Site Engineer cannot archive');
        $this->assertSame('12.00', (string) $this->value('SELECT reorder_level FROM resources WHERE id = ?', [$id]));
    }

    public function testMaterialRequestWorkflow(): void
    {
        $se = $this->as('se1');
        $pm = $this->as('pm1');
        $need = date('Y-m-d', strtotime('+4 days'));
        $this->assertSame(422, $se->post('/api/v1/projects/1/material-requests', ['resource_id' => 1, 'quantity' => 5, 'required_by' => $need])['status'], 'Only materials can be requested');
        $this->assertSame(422, $se->post('/api/v1/projects/1/material-requests', ['resource_id' => 4, 'quantity' => 5, 'required_by' => date('Y-m-d', strtotime('-1 day'))])['status'], 'Not in the past');
        $id = $se->post('/api/v1/projects/1/material-requests', ['resource_id' => 4, 'quantity' => 80, 'required_by' => $need, 'remarks' => 'base course'])['json']['data']['id'];
        $this->assertSame(403, $se->post("/api/v1/material-requests/{$id}/approve")['status'], 'Site Engineer cannot approve');
        $this->assertSame(409, $se->post("/api/v1/material-requests/{$id}/deliver")['status'], 'Not delivered before approval');
        $this->assertTrue(self::ok($pm->post("/api/v1/material-requests/{$id}/approve")));
        $this->assertSame(409, $pm->post("/api/v1/material-requests/{$id}/approve")['status'], 'Cannot approve twice');
        $this->assertSame(403, $se->post("/api/v1/material-requests/{$id}/cancel", ['reason' => 'changed mind'])['status'], 'Requester cannot cancel after approval');
        $before = (float) $this->value("SELECT COALESCE(SUM(CASE movement WHEN 'received' THEN quantity WHEN 'used' THEN -quantity END), 0) FROM resource_usage WHERE resource_id = 4");
        $this->assertTrue(self::ok($se->post("/api/v1/material-requests/{$id}/deliver", ['quantity' => '75'])));
        $after = (float) $this->value("SELECT COALESCE(SUM(CASE movement WHEN 'received' THEN quantity WHEN 'used' THEN -quantity END), 0) FROM resource_usage WHERE resource_id = 4");
        $this->assertEqualsWithDelta(75.0, $after - $before, 0.001, 'Delivery adds the delivered quantity to the store');
        $this->assertSame('delivered', $this->value('SELECT status FROM material_requests WHERE id = ?', [$id]));

        // PM cannot approve their own request; the requester may cancel while it waits, with a reason.
        $own = $pm->post('/api/v1/projects/1/material-requests', ['resource_id' => 3, 'quantity' => 50, 'required_by' => $need])['json']['data']['id'];
        $this->assertSame(409, $pm->post("/api/v1/material-requests/{$own}/approve")['status']);
        $mine = $se->post('/api/v1/projects/1/material-requests', ['resource_id' => 3, 'quantity' => 10, 'required_by' => $need])['json']['data']['id'];
        $this->assertSame(422, $se->post("/api/v1/material-requests/{$mine}/cancel", ['reason' => ''])['status']);
        $this->assertTrue(self::ok($se->post("/api/v1/material-requests/{$mine}/cancel", ['reason' => 'ordered twice'])));
        $this->assertSame('cancelled', $this->value('SELECT status FROM material_requests WHERE id = ?', [$mine]));
    }

    public function testOfflineSyncOfResourceEntriesIsIdempotent(): void
    {
        $se = $this->as('se1');
        $u1 = self::uuid();
        $u2 = self::uuid();
        $items = [
            ['client_uuid' => $u1, 'type' => 'usage', 'resource_id' => 2, 'body' => ['usage_date' => date('Y-m-d'), 'movement' => 'attendance', 'quantity' => 7]],
            ['client_uuid' => $u2, 'type' => 'equipment_status', 'resource_id' => 5, 'body' => ['equipment_status' => 'idle']],
        ];
        $r = $se->post('/api/v1/sync', ['items' => $items]);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame([201, 201], array_column($r['json']['data'], 'status'));
        $again = $se->post('/api/v1/sync', ['items' => $items])['json']['data'];
        $this->assertTrue($again[0]['replayed'] && $again[1]['replayed']);
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM resource_usage WHERE client_uuid = ?', [$u1]), 'No duplicate after replay');
        $this->assertSame('idle', $this->value('SELECT equipment_status FROM resources WHERE id = 5'));
        $bad = $se->post('/api/v1/sync', ['items' => [['client_uuid' => self::uuid(), 'type' => 'usage', 'resource_id' => 8, 'body' => ['usage_date' => date('Y-m-d'), 'movement' => 'used', 'quantity' => 1]]]]);
        $this->assertSame(404, $bad['json']['data'][0]['status'], 'Offline items are scope-checked like direct calls');
    }

    public function testClosedProjectRefusesEntriesAndReportRespectsRoles(): void
    {
        $se3 = $this->as('se3');
        // Project 3 is completed in the demo data; se3 is assigned to it.
        $rid = (int) $this->value("SELECT id FROM resources WHERE project_id = 3 LIMIT 1");
        if ($rid > 0) {
            $this->assertSame(409, $se3->post("/api/v1/resources/{$rid}/usage", ['usage_date' => date('Y-m-d'), 'movement' => 'attendance', 'quantity' => 1])['status']);
        }
        $this->assertSame(409, $se3->post('/api/v1/projects/3/resources', ['category' => 'labour', 'name' => 'Painters'])['status'], 'Completed project is closed');
        $csv = $this->as('qs1')->get('/api/v1/reports/resources?project_id=1&format=csv');
        $this->assertSame(200, $csv['status']);
        $this->assertStringContainsString('Value used (GH₵)', $csv['body']);
        $this->assertStringContainsString('Cement (50 kg bag)', $csv['body']);
        $this->assertSame(403, $this->as('client1')->get('/api/v1/reports/resources?project_id=1&format=csv')['status']);
    }
}
