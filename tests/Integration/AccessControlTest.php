<?php

declare(strict_types=1);

namespace Lubie\Tests\Integration;

use Lubie\Tests\Support\IntegrationTestCase;

/** IT-ACC: project scope (IDOR), field-level filtering, hard role rules (FR-03, FR-04, NFR-02). */
final class AccessControlTest extends IntegrationTestCase
{
    private const MONEY = '/(cost|budget|amount|price|rate|expend|valuation|certif|payment|^ac$|^ev$|^pv$|^bac$|^cv$|^cpi$|^sv$|variance|consumed|contract_sum|supplier)/i';

    public function testOutOfScopeProjectIdsReturn404AndAreAudited(): void
    {
        $before = (int) $this->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'forbidden'");
        $se = $this->as('se1');                       // assigned to project 1 only
        foreach (['/api/v1/projects/2', '/api/v1/projects/2/dashboard', '/api/v1/projects/2/activities', '/api/v1/projects/2/milestones', '/api/v1/activities/7/progress'] as $p) {
            $this->assertSame(404, $se->get($p)['status'], $p);
        }
        $r = $se->post('/api/v1/activities/7/progress', ['record_date' => date('Y-m-d'), 'percent_complete' => 50]);
        $this->assertSame(404, $r['status'], 'Cannot write to another project');
        $client = $this->as('client1');               // project 1
        $this->assertSame(404, $client->get('/api/v1/client/summary?project_id=2')['status']);
        $this->assertSame(404, $client->get('/api/v1/reports/client-summary?project_id=2&format=csv')['status']);
        $pm2 = $this->as('pm2');
        $this->assertSame(404, $pm2->get('/api/v1/projects/1/progress-queue')['status']);
        $this->assertSame(404, $pm2->post('/api/v1/projects/1/assignments', ['user_id' => 8])['status']);
        $this->assertGreaterThan($before, (int) $this->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'forbidden'"));
    }

    public function testNonNumericIdsAre404(): void
    {
        $this->assertSame(404, $this->as('pm1')->get('/api/v1/projects/1abc')['status']);
    }

    public function testSiteEngineerSeesNoMoneyAnywhere(): void
    {
        $se = $this->as('se1');
        foreach (['/api/v1/projects', '/api/v1/projects/1', '/api/v1/projects/1/dashboard', '/api/v1/projects/1/activities', '/api/v1/projects/1/milestones',
                     '/api/v1/projects/1/my-progress', '/api/v1/alerts', '/api/v1/lookups', '/api/v1/auth/me', '/api/v1/activities/3/progress'] as $p) {
            $r = $se->get($p);
            $this->assertSame(200, $r['status'], $p);
            foreach (self::allKeys($r['json']) as $k) {
                $this->assertDoesNotMatchRegularExpression(self::MONEY, $k, "{$p} leaks {$k} to a Site Engineer");
            }
        }
        foreach ($se->get('/api/v1/alerts')['json']['data'] as $a) {
            $this->assertStringNotContainsString('GH₵', $a['title'] . $a['description'], 'No money in site alerts');
            $this->assertNotContains($a['rule_code'] ?? '', ['A5', 'A6', 'A6b', 'A7', 'A9', 'A12']);
        }
        foreach (['/api/v1/projects/1/budgets', '/api/v1/projects/1/expenditures', '/api/v1/projects/1/certificates', '/api/v1/reports', '/api/v1/dashboard/portfolio'] as $p) {
            $this->assertSame(403, $se->get($p)['status'], $p);
        }
    }

    public function testSiteEngineerCanCreateActivityButCostIsIgnored(): void
    {
        $se = $this->as('se1');
        $r = $se->post('/api/v1/projects/1/activities', ['code' => 'SE1', 'name' => 'Kerb laying', 'planned_start' => date('Y-m-d'),
            'planned_finish' => date('Y-m-d', strtotime('+10 days')), 'budgeted_cost' => '99999.00']);
        $this->assertSame(201, $r['status'], $r['body']);
        $this->assertNull($this->value("SELECT budgeted_cost FROM activities WHERE project_id = 1 AND code = 'SE1'"));
    }

    public function testClientSummaryIsAnAllowList(): void
    {
        $c = $this->as('client1');
        $r = $c->get('/api/v1/client/summary?project_id=1');
        $this->assertSame(200, $r['status']);
        $keys = self::allKeys($r['json']['data']);
        foreach (['budget', 'expenditure', 'labour', 'supplier', 'revision', 'audit', 'cpi', 'spi', 'ac', 'ev', 'pv', 'cost', 'is_internal', 'sms_status', 'remarks'] as $bad) {
            $this->assertNotContains($bad, $keys, "Client sees {$bad}");
        }
        $this->assertArrayHasKey('payments', $r['json']['data']);
        foreach ($c->get('/api/v1/alerts')['json']['data'] as $a) {
            $this->assertSame('A4', $a['rule_code'] ?? 'A4', 'Clients only receive client-safe notices');
            $this->assertSame('none', $a['sms_status'] ?? 'none', 'Clients never get SMS');
        }
        foreach (['/api/v1/projects/1/dashboard', '/api/v1/projects/1/activities', '/api/v1/projects/1/budgets', '/api/v1/projects/1/certificates', '/api/v1/audit', '/api/v1/users'] as $p) {
            $this->assertSame(403, $c->get($p)['status'], $p);
        }
    }

    public function testAdministratorHasNoFinancialDetailAndDirectorNoAudit(): void
    {
        $admin = $this->as('admin');
        foreach (['/api/v1/projects/1/budgets', '/api/v1/projects/1/expenditures', '/api/v1/projects/1/certificates', '/api/v1/projects/1/dashboard', '/api/v1/dashboard/portfolio'] as $p) {
            $this->assertSame(403, $admin->get($p)['status'], $p);
        }
        $this->assertSame(200, $admin->get('/api/v1/audit')['status']);
        $this->assertSame(403, $this->as('director')->get('/api/v1/audit')['status']);
    }

    public function testQsCannotApproveProgressAndPmCannotApproveBudget(): void
    {
        $qs = $this->as('qs1');
        $this->assertSame(403, $qs->post('/api/v1/progress/1/approve')['status']);
        $pm = $this->as('pm2');
        $this->assertSame(403, $pm->post('/api/v1/budgets/5/approve', ['note' => 'x'])['status']);
        $this->assertSame(403, $pm->post('/api/v1/projects/2/expenditures', [])['status'], 'PM does not record expenditure');
    }

    public function testDeactivatedUserSessionIsRejected(): void
    {
        $c = $this->as('se2');
        $this->assertSame(200, $c->get('/api/v1/projects')['status']);
        $admin = $this->as('admin');
        $this->assertSame(204, $admin->call('PATCH', '/api/v1/users/8', ['status' => 'deactivated'])['status']);
        $this->assertSame(401, $c->get('/api/v1/projects')['status']);
        $admin->call('PATCH', '/api/v1/users/8', ['status' => 'active']);
    }
}
