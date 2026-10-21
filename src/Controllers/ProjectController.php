<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Repositories\ProjectRepository;
use Lubie\Security\Authorizer;
use Lubie\Services\Presenter;
use Lubie\Services\ProjectService;
use Lubie\Services\ScheduleService;

final class ProjectController extends BaseController
{
    public function index(Request $r): Response
    {
        return $this->ok($this->c->get(ProjectService::class)->listFor($this->user(), $r->queryParam('archived') === '1'));
    }

    public function show(Request $r): Response
    {
        $u = $this->user();
        $id = $r->intParam('id');
        $this->c->get(Authorizer::class)->requireProject($u, $id, true);
        return $this->ok(Presenter::project($this->c->get(ProjectRepository::class)->find($id), $u->role));
    }

    public function store(Request $r): Response
    {
        return $this->created($this->c->get(ProjectService::class)->create($this->user(), $r->body));
    }

    public function update(Request $r): Response
    {
        $this->c->get(ProjectService::class)->update($this->user(), $r->intParam('id'), $r->body);
        return Response::noContent();
    }

    public function status(Request $r): Response
    {
        $this->c->get(ProjectService::class)->changeStatus($this->user(), $r->intParam('id'), (string) $r->input('status', ''), $r->input('reason'));
        return Response::noContent();
    }

    public function assignments(Request $r): Response
    {
        return $this->ok($this->c->get(ProjectService::class)->assignments($this->user(), $r->intParam('id')));
    }

    public function assignable(Request $r): Response
    {
        return $this->ok($this->c->get(ProjectService::class)->assignable($this->user(), $r->intParam('id')));
    }

    public function assign(Request $r): Response
    {
        $this->c->get(ProjectService::class)->assign($this->user(), $r->intParam('id'), (int) $r->input('user_id', 0));
        return Response::json(['data' => ['assigned' => true]], 201);
    }

    public function unassign(Request $r): Response
    {
        $this->c->get(ProjectService::class)->unassign($this->user(), $r->intParam('id'), $r->intParam('userId'));
        return Response::noContent();
    }

    public function activities(Request $r): Response
    {
        return $this->ok($this->c->get(ScheduleService::class)->activities($this->user(), $r->intParam('id')));
    }

    public function storeActivity(Request $r): Response
    {
        return $this->created($this->c->get(ScheduleService::class)->createActivity($this->user(), $r->intParam('id'), $r->body));
    }

    public function updateActivity(Request $r): Response
    {
        $this->c->get(ScheduleService::class)->updateActivity($this->user(), $r->intParam('id'), $r->body);
        return Response::noContent();
    }

    public function archiveActivity(Request $r): Response
    {
        $this->c->get(ScheduleService::class)->archiveActivity($this->user(), $r->intParam('id'), (string) $r->input('reason', ''));
        return Response::noContent();
    }

    public function dependencies(Request $r): Response
    {
        $ids = $r->input('predecessor_ids', []);
        $this->c->get(ScheduleService::class)->setDependencies($this->user(), $r->intParam('id'), is_array($ids) ? array_values($ids) : []);
        return Response::noContent();
    }

    public function milestones(Request $r): Response
    {
        return $this->ok($this->c->get(ScheduleService::class)->milestones($this->user(), $r->intParam('id')));
    }

    public function storeMilestone(Request $r): Response
    {
        return $this->created($this->c->get(ScheduleService::class)->createMilestone($this->user(), $r->intParam('id'), $r->body));
    }

    public function updateMilestone(Request $r): Response
    {
        $this->c->get(ScheduleService::class)->updateMilestone($this->user(), $r->intParam('id'), $r->body);
        return Response::noContent();
    }
}
