<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Repositories\CostRepository;
use Lubie\Repositories\ProgressRepository;
use Lubie\Services\AlertQueryService;
use Lubie\Services\DashboardService;

final class DashboardController extends BaseController
{
    public function project(Request $r): Response
    {
        return $this->ok($this->c->get(DashboardService::class)->project($this->user(), $r->intParam('id')));
    }

    public function portfolio(Request $r): Response
    {
        return $this->ok($this->c->get(DashboardService::class)->portfolio($this->user()));
    }

    public function admin(Request $r): Response
    {
        return $this->ok($this->c->get(DashboardService::class)->adminSummary());
    }

    public function client(Request $r): Response
    {
        return $this->ok($this->c->get(DashboardService::class)->clientSummary($this->user(), (int) $r->queryParam('project_id', 0)));
    }

    public function changes(Request $r): Response
    {
        $pid = $r->queryParam('project_id');
        return $this->ok($this->c->get(DashboardService::class)->changes($this->user(), $pid !== null && $pid !== '' ? (int) $pid : null, (int) $r->queryParam('since', -1)));
    }

    public function alerts(Request $r): Response
    {
        $pid = $r->queryParam('project_id');
        return $this->ok($this->c->get(AlertQueryService::class)->list($this->user(), (string) $r->queryParam('status', 'open'), $pid ? (int) $pid : null));
    }

    public function readAlert(Request $r): Response
    {
        $this->c->get(AlertQueryService::class)->mark($this->user(), $r->intParam('id'), 'read');
        return Response::noContent();
    }

    public function ackAlert(Request $r): Response
    {
        $this->c->get(AlertQueryService::class)->mark($this->user(), $r->intParam('id'), 'acknowledge');
        return Response::noContent();
    }

    public function lookups(Request $r): Response
    {
        $out = [
            'delay_reasons' => $this->c->get(ProgressRepository::class)->delayReasons(),
            'units' => ['bag', 't', 'm3', 'm2', 'm', 'pcs', 'kg', 'litre', 'trip', 'hr', 'persons'],
            'project_statuses' => ['planning', 'active', 'on_hold', 'completed', 'archived'],
        ];
        // Cost lists only for roles that handle cost (need to know).
        if ($this->c->get(\Lubie\Security\Authorizer::class)->can($this->user(), 'cost.view')) {
            $out['expenditure_categories'] = $this->c->get(CostRepository::class)->categories();
        }
        return $this->ok($out);
    }
}
