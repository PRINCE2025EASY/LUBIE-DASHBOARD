<?php

declare(strict_types=1);

namespace Lubie\Tests\Integration;

use Lubie\Tests\Support\IntegrationTestCase;

/** IT-PRJ: projects, status changes, assignments, activities, dependencies, milestones (FR-06, FR-07, FR-08, FR-13). */
final class ProjectScheduleTest extends IntegrationTestCase
{
    private static function project(array $over = []): array
    {
        return $over + ['code' => 'T-' . random_int(100, 999), 'name' => 'Demo test road', 'project_type' => 'Road and drainage', 'location' => 'Demo town',
            'client_name' => 'Demo client', 'contract_sum' => '1,000,000.00', 'planned_start' => date('Y-m-d'), 'planned_finish' => date('Y-m-d', strtotime('+120 days')), 'payment_period_days' => 30];
    }

    public function testPmCreatesProjectAndIsAutoAssigned(): void
    {
        $pm = $this->as('pm2');
        $bad = $pm->post('/api/v1/projects', self::project(['planned_finish' => date('Y-m-d', strtotime('-1 day')), 'contract_sum' => '-5']));
        $this->assertSame(422, $bad['status']);
        $this->assertArrayHasKey('planned_finish', $bad['json']['error']['fields']);
        $r = $pm->post('/api/v1/projects', self::project(['code' => 'T-PM2']));
        $this->assertSame(201, $r['status'], $r['body']);
        $id = $r['json']['data']['id'];
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM project_assignments WHERE project_id = ? AND user_id = 4', [$id]));
        $this->assertSame(422, $pm->post('/api/v1/projects', self::project(['code' => 'T-PM2']))['status'], 'Duplicate code');
        $this->assertSame(204, $pm->call('PATCH', "/api/v1/projects/{$id}", self::project(['code' => 'T-PM2', 'name' => 'Renamed demo road']))['status']);
        $this->assertSame('Renamed demo road', $this->value('SELECT name FROM projects WHERE id = ?', [$id]));
        $this->assertSame(404, $this->as('pm1')->call('PATCH', "/api/v1/projects/{$id}", self::project())['status'], 'Other PM cannot edit');
    }

    public function testStatusTransitionsFollowTheLifecycle(): void
    {
        $pm = $this->as('pm2');
        $id = $pm->post('/api/v1/projects', self::project(['code' => 'T-ST']))['json']['data']['id'];
        $this->assertSame(409, $pm->post("/api/v1/projects/{$id}/status", ['status' => 'completed'])['status'], 'planning cannot jump to completed');
        foreach (['active', 'on_hold', 'active', 'completed'] as $s) {
            $this->assertSame(204, $pm->post("/api/v1/projects/{$id}/status", ['status' => $s])['status'], $s);
        }
        $this->assertSame('completed', $this->value('SELECT status FROM projects WHERE id = ?', [$id]));
        $act = $pm->post("/api/v1/projects/{$id}/activities", ['code' => 'X1', 'name' => 'Late item', 'planned_start' => date('Y-m-d'), 'planned_finish' => date('Y-m-d')]);
        $this->assertSame(409, $act['status'], 'Completed projects are closed for new activities');
        $this->assertGreaterThan(0, (int) $this->value("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'project' AND entity_id = ? AND action = 'status_change'", [(string) $id]));
    }

    public function testAssignmentRules(): void
    {
        $admin = $this->as('admin');
        $this->assertSame(422, $admin->post('/api/v1/projects/2/assignments', ['user_id' => 2])['status'], 'Directors are never assigned');
        $this->assertSame(422, $admin->post('/api/v1/projects/2/assignments', ['user_id' => 13])['status'], 'Deactivated user');
        $this->assertSame(409, $admin->post('/api/v1/projects/2/assignments', ['user_id' => 5])['status'], 'Already assigned');
        $this->assertSame(201, $admin->post('/api/v1/projects/2/assignments', ['user_id' => 9])['status']);
        $se3 = $this->as('se3');
        $this->assertSame(200, $se3->get('/api/v1/projects/2')['status'], 'Access starts at once');
        $this->assertSame(204, $admin->call('DELETE', '/api/v1/projects/2/assignments/9')['status']);
        $this->assertSame(404, $se3->get('/api/v1/projects/2')['status'], 'Access ends at once');
        $this->assertSame(200, $admin->get('/api/v1/projects/2/assignments')['status']);
    }

    public function testActivityEditArchiveAndDependencyLoops(): void
    {
        $pm = $this->as('pm1');
        $this->assertSame(422, $pm->call('PATCH', '/api/v1/activities/2', ['actual_start' => '2026-05-10', 'actual_finish' => '2026-05-01'])['status']);
        $this->assertSame(204, $pm->call('PATCH', '/api/v1/activities/6', ['name' => 'Drains and outfalls', 'budgeted_cost' => '155000.00'])['status']);
        $this->assertSame('155000.00', $this->value('SELECT budgeted_cost FROM activities WHERE id = 6'));
        $this->assertSame(204, $pm->call('PUT', '/api/v1/activities/3/dependencies', ['predecessor_ids' => [2]])['status']);
        $loop = $pm->call('PUT', '/api/v1/activities/2/dependencies', ['predecessor_ids' => [3]]);
        $this->assertSame(422, $loop['status'], 'Cycle refused');
        $this->assertSame(422, $pm->call('PUT', '/api/v1/activities/3/dependencies', ['predecessor_ids' => [7]])['status'], 'Other project');
        $se = $this->as('se1');
        $this->assertSame(403, $se->post('/api/v1/activities/6/archive', ['reason' => 'x'])['status'], 'Only PM archives');
        $this->assertSame(204, $pm->post('/api/v1/activities/6/archive', ['reason' => 'Merged into R05'])['status']);
        $this->assertSame(409, $se->post('/api/v1/activities/6/progress', ['record_date' => date('Y-m-d'), 'percent_complete' => 90])['status'], 'No progress on archived work');
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM activities WHERE id = 6'), 'Archive never deletes');
    }

    public function testMilestonesAchievedDateAndScope(): void
    {
        $pm = $this->as('pm1');
        $r = $pm->post('/api/v1/projects/1/milestones', ['name' => 'Handover of section A', 'due_date' => date('Y-m-d', strtotime('+10 days')), 'activity_id' => 7]);
        $this->assertSame(422, $r['status'], 'Activity from another project');
        $id = $pm->post('/api/v1/projects/1/milestones', ['name' => 'Handover of section A', 'due_date' => date('Y-m-d', strtotime('+10 days'))])['json']['data']['id'];
        $this->assertSame(422, $pm->call('PATCH', "/api/v1/milestones/{$id}", ['name' => 'Handover of section A', 'due_date' => date('Y-m-d', strtotime('+10 days')), 'achieved_date' => date('Y-m-d', strtotime('+1 day'))])['status'], 'Cannot be achieved in the future');
        $this->assertSame(204, $pm->call('PATCH', "/api/v1/milestones/{$id}", ['name' => 'Handover of section A', 'due_date' => date('Y-m-d', strtotime('+10 days')), 'achieved_date' => date('Y-m-d')])['status']);
        $states = array_column($this->as('client1')->get('/api/v1/client/summary?project_id=1')['json']['data']['milestones'], 'state', 'name');
        $this->assertSame('achieved', $states['Handover of section A']);
    }
}
