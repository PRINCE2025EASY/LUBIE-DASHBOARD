<?php

declare(strict_types=1);

namespace Lubie\Tests\Integration;

use Lubie\Tests\Support\IntegrationTestCase;

/** IT-WF: progress approval, offline sync idempotency, budgets, expenditure, payments, alerts, polling, exports. */
final class WorkflowTest extends IntegrationTestCase
{
    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public function testOnlyApprovedProgressChangesKpis(): void
    {
        $pm = $this->as('pm1');
        $se = $this->as('se1');
        $before = $pm->get('/api/v1/projects/1/dashboard')['json']['data'];
        $seq0 = (int) $before['change_seq'];
        $r = $se->post('/api/v1/activities/6/progress', ['record_date' => date('Y-m-d'), 'percent_complete' => 40, 'remarks' => 'Drains half done']);
        $this->assertSame(201, $r['status'], $r['body']);
        $id = $r['json']['data']['id'];
        $mid = $pm->get('/api/v1/projects/1/dashboard')['json']['data'];
        $this->assertSame($before['kpis']['completion_pct'], $mid['kpis']['completion_pct'], 'Submitted progress does not count');
        $this->assertGreaterThan($seq0, (int) $mid['change_seq'], 'Polling token moves on every save');
        $queue = $pm->get('/api/v1/projects/1/progress-queue')['json']['data'];
        $this->assertContains($id, array_column($queue, 'id'));
        $this->assertSame(200, $pm->post("/api/v1/progress/{$id}/approve")['status']);
        $after = $pm->get('/api/v1/projects/1/dashboard')['json']['data'];
        $this->assertGreaterThan($before['kpis']['completion_pct'], $after['kpis']['completion_pct']);
        $this->assertSame(409, $pm->post("/api/v1/progress/{$id}/approve")['status'], 'Cannot decide twice');
        $changes = $pm->get('/api/v1/changes?project_id=1&since=' . $seq0)['json']['data'];
        $this->assertTrue($changes['changed']);
    }

    public function testReturnNeedsReasonAndResubmissionLinks(): void
    {
        $pm = $this->as('pm1');
        $se = $this->as('se1');
        $id = $se->post('/api/v1/activities/5/progress', ['record_date' => date('Y-m-d'), 'percent_complete' => 10])['json']['data']['id'];
        $this->assertSame(422, $pm->post("/api/v1/progress/{$id}/return", ['return_reason' => ''])['status']);
        $this->assertSame(200, $pm->post("/api/v1/progress/{$id}/return", ['return_reason' => 'Photo shows less than 10 %'])['status']);
        $mine = $se->get('/api/v1/projects/1/my-progress')['json']['data'];
        $row = array_values(array_filter($mine, static fn ($m) => $m['id'] === $id))[0];
        $this->assertSame('returned', $row['status']);
        $re = $se->post('/api/v1/activities/5/progress', ['record_date' => date('Y-m-d'), 'percent_complete' => 5, 'resubmission_of' => $id]);
        $this->assertSame(201, $re['status'], $re['body']);
    }

    public function testProgressValidation(): void
    {
        $se = $this->as('se1');
        $future = $se->post('/api/v1/activities/5/progress', ['record_date' => date('Y-m-d', strtotime('+2 days')), 'percent_complete' => 10]);
        $this->assertSame(422, $future['status']);
        $this->assertArrayHasKey('record_date', $future['json']['error']['fields']);
        $over = $se->post('/api/v1/activities/5/progress', ['record_date' => date('Y-m-d'), 'percent_complete' => 120]);
        $this->assertArrayHasKey('percent_complete', $over['json']['error']['fields']);
    }

    public function testStoppageRaisesCriticalAlertForDirectorButNotClient(): void
    {
        $se = $this->as('se3');
        $r = $se->post('/api/v1/activities/4/progress', ['record_date' => date('Y-m-d'), 'percent_complete' => 30, 'delay_reason_id' => 10, 'remarks' => 'Trench collapse, no injuries']);
        $this->assertSame(201, $r['status'], $r['body']);
        $dir = $this->as('director')->get('/api/v1/alerts')['json']['data'];
        $this->assertNotEmpty(array_filter($dir, static fn ($a) => str_contains($a['title'], 'Safety')));
        $client = $this->as('client1')->get('/api/v1/alerts')['json']['data'];
        $this->assertEmpty(array_filter($client, static fn ($a) => str_contains($a['title'], 'Safety')));
    }

    public function testOfflineSyncIsIdempotent(): void
    {
        $se = $this->as('se1');
        $u1 = self::uuid();
        $u2 = self::uuid();
        $items = [
            ['client_uuid' => $u1, 'type' => 'progress', 'activity_id' => 2, 'body' => ['record_date' => date('Y-m-d'), 'percent_complete' => 55]],
            ['client_uuid' => $u2, 'type' => 'progress', 'activity_id' => 7, 'body' => ['record_date' => date('Y-m-d'), 'percent_complete' => 55]],   // other project
            ['client_uuid' => 'not-a-uuid', 'type' => 'progress'],
        ];
        $r = $se->post('/api/v1/sync', ['items' => $items]);
        $this->assertSame(200, $r['status'], $r['body']);
        $res = $r['json']['data'];
        $this->assertSame([201, 404, 422], array_column($res, 'status'), 'Server re-checks scope and validation for every item');
        $again = $se->post('/api/v1/sync', ['items' => $items])['json']['data'];
        $this->assertTrue($again[0]['replayed']);
        $this->assertSame($res[0]['entity_id'], $again[0]['entity_id']);
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM progress_records WHERE client_uuid = ?', [$u1]), 'No duplicate on replay');
        $other = $this->as('se3')->post('/api/v1/sync', ['items' => [$items[0]]])['json']['data'];
        $this->assertSame(409, $other[0]['status'], 'Another user cannot reuse a UUID');
        $this->assertSame(422, $se->post('/api/v1/sync', ['items' => array_fill(0, 51, $items[0])])['status']);
        $this->assertSame(403, $this->as('client1')->post('/api/v1/sync', ['items' => []])['status']);
    }

    public function testBudgetRevisionWorkflowAndSeparationOfDuties(): void
    {
        $qs = $this->as('qs1');
        $dir = $this->as('director');
        $r = $qs->post('/api/v1/projects/1/budgets', ['amount' => '2,650,000.00', 'reason' => 'Variation order 4: extra culvert']);
        $this->assertSame(201, $r['status'], $r['body']);
        $id = $r['json']['data']['id'];
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM notifications WHERE dedup_key = ? AND cleared_at IS NULL", ["A12:budget:{$id}"]), 'A12 raised');
        $this->assertSame(422, $dir->post("/api/v1/budgets/{$id}/reject", ['note' => ''])['status'], 'Reject needs a note');
        $this->assertSame(204, $dir->post("/api/v1/budgets/{$id}/approve", ['note' => 'Agreed'])['status']);
        $b = $qs->get('/api/v1/projects/1/budgets')['json']['data'];
        $this->assertSame('2650000.00', $b['current']['amount']);
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM budgets WHERE project_id = 1 AND is_current = 1'));
        $this->assertSame('superseded', $this->value('SELECT status FROM budgets WHERE id = 2'));
        $this->assertSame(0, (int) $this->value("SELECT COUNT(*) FROM notifications WHERE dedup_key = ? AND cleared_at IS NULL", ["A12:budget:{$id}"]), 'A12 cleared');
        $this->assertSame(409, $dir->post("/api/v1/budgets/{$id}/approve", [])['status'], 'Already decided');
        $this->assertSame(403, $dir->post('/api/v1/projects/1/budgets', ['amount' => '1', 'reason' => 'xyz'])['status'], 'Director cannot request');
    }

    public function testExpenditureNeedsConfirmationAndVoidKeepsHistory(): void
    {
        $qs = $this->as('qs1');
        $body = ['expense_date' => date('Y-m-d'), 'amount' => '12,500.50', 'category_id' => 1, 'description' => 'Cement 250 bags', 'reference_no' => 'PV-T-001', 'supplier_name' => 'Demo Supplier Ltd'];
        $first = $qs->post('/api/v1/projects/1/expenditures', $body);
        $this->assertSame(422, $first['status']);
        $this->assertArrayHasKey('confirmed', $first['json']['error']['fields']);
        $r = $qs->post('/api/v1/projects/1/expenditures', $body + ['confirmed' => true]);
        $this->assertSame(201, $r['status'], $r['body']);
        $this->assertSame(409, $qs->post('/api/v1/projects/1/expenditures', $body + ['confirmed' => true])['status'], 'Duplicate voucher refused');
        $id = $r['json']['data']['id'];
        $this->assertSame('12500.50', $this->value('SELECT amount FROM expenditures WHERE id = ?', [$id]));
        $this->assertSame(422, $qs->post("/api/v1/expenditures/{$id}/void", [])['status']);
        $this->assertSame(204, $qs->post("/api/v1/expenditures/{$id}/void", ['void_reason' => 'Entered on wrong project'])['status']);
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM expenditures WHERE id = ?', [$id]), 'Void never deletes');
        $this->assertGreaterThan(0, (int) $this->value("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'expenditure' AND entity_id = ?", [(string) $id]));
    }

    public function testCertificatePaymentCannotExceedCertified(): void
    {
        $qs = $this->as('qs1');
        $r = $qs->post('/api/v1/projects/1/certificates', ['cert_no' => 'IPC-T1', 'valuation_amount' => '100000.00', 'submitted_date' => date('Y-m-d')]);
        $this->assertSame(201, $r['status'], $r['body']);
        $id = $r['json']['data']['id'];
        $this->assertSame(204, $qs->post("/api/v1/certificates/{$id}/certify", ['amount_certified' => '95000.00', 'certified_date' => date('Y-m-d')])['status']);
        $this->assertSame(201, $qs->post("/api/v1/certificates/{$id}/payments", ['paid_date' => date('Y-m-d'), 'amount' => '60000.00', 'reference_no' => 'CHQ-1'])['status']);
        $over = $qs->post("/api/v1/certificates/{$id}/payments", ['paid_date' => date('Y-m-d'), 'amount' => '35000.01', 'reference_no' => 'CHQ-2']);
        $this->assertSame(422, $over['status']);
        $this->assertStringContainsString('GH₵ 35,000.00', $over['json']['error']['fields']['amount']);
        $this->assertSame(201, $qs->post("/api/v1/certificates/{$id}/payments", ['paid_date' => date('Y-m-d'), 'amount' => '35000.00', 'reference_no' => 'CHQ-2'])['status']);
        $this->assertSame('paid', $this->value('SELECT status FROM payment_certificates WHERE id = ?', [$id]));
    }

    public function testExportsRespectRolesAndAreSafe(): void
    {
        $qs = $this->as('qs1');
        $csv = $qs->get('/api/v1/reports/expenditure?project_id=1&format=csv');
        $this->assertSame(200, $csv['status']);
        $this->assertStringContainsString('text/csv', $csv['headers']['Content-Type']);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv['body']);
        $pdf = $qs->get('/api/v1/reports/status?project_id=1&format=pdf');
        $this->assertSame(200, $pdf['status'], substr($pdf['body'], 0, 300));
        $this->assertStringStartsWith('%PDF', $pdf['body']);
        $html = $qs->get('/api/v1/reports/status?project_id=1&format=html');
        $this->assertSame(200, $html['status']);
        $this->assertStringNotContainsString('script-src', $html['headers']['Content-Security-Policy']);
        $client = $this->as('client1');
        $this->assertSame(200, $client->get('/api/v1/reports/client-summary?project_id=1&format=pdf')['status']);
        $this->assertSame(403, $client->get('/api/v1/reports/expenditure?project_id=1&format=csv')['status']);
        $this->assertSame(403, $this->as('director')->get('/api/v1/reports/audit?format=csv')['status']);
        $this->assertSame(200, $this->as('admin')->get('/api/v1/reports/audit?format=csv')['status']);
    }

    public function testThresholdLimitsAndProposals(): void
    {
        $pm = $this->as('pm1');
        $this->assertSame(422, $pm->call('PUT', '/api/v1/projects/1/thresholds/cpi_min', ['value' => '5'])['status'], 'Outside the Director limits');
        $this->assertSame(204, $pm->call('PUT', '/api/v1/projects/1/thresholds/cpi_min', ['value' => '0.85'])['status']);
        $qs = $this->as('qs1');
        $p = $qs->post('/api/v1/threshold-proposals', ['setting_key' => 'budget_warning_pct', 'value' => '75', 'project_id' => 1]);
        $this->assertSame(201, $p['status'], $p['body']);
        $this->assertSame(204, $this->as('director')->post('/api/v1/threshold-proposals/' . $p['json']['data']['id'] . '/decide', ['approve' => true])['status']);
    }
}
