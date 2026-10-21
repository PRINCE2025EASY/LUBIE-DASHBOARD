<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Services\CostService;

final class CostController extends BaseController
{
    private function svc(): CostService
    {
        return $this->c->get(CostService::class);
    }

    public function budgets(Request $r): Response
    {
        return $this->ok($this->svc()->budgets($this->user(), $r->intParam('id')));
    }

    public function requestBudget(Request $r): Response
    {
        return $this->created($this->svc()->requestBudget($this->user(), $r->intParam('id'), $r->body));
    }

    public function submitBudget(Request $r): Response
    {
        $this->svc()->submitBudget($this->user(), $r->intParam('id'));
        return Response::noContent();
    }

    public function approveBudget(Request $r): Response
    {
        $this->svc()->decideBudget($this->user(), $r->intParam('id'), true, $r->input('note'));
        return Response::noContent();
    }

    public function rejectBudget(Request $r): Response
    {
        $this->svc()->decideBudget($this->user(), $r->intParam('id'), false, $r->input('note'));
        return Response::noContent();
    }

    public function pendingBudgets(Request $r): Response
    {
        return $this->ok($this->svc()->pendingForDirector());
    }

    public function expenditures(Request $r): Response
    {
        [$page, $per] = $this->pageArgs($r);
        return Response::json($this->svc()->expenditures($this->user(), $r->intParam('id'), $page, $per));
    }

    public function recordExpenditure(Request $r): Response
    {
        return $this->created($this->svc()->recordExpenditure($this->user(), $r->intParam('id'), $r->body));
    }

    public function voidExpenditure(Request $r): Response
    {
        $this->svc()->voidExpenditure($this->user(), $r->intParam('id'), (string) $r->input('void_reason', ''));
        return Response::noContent();
    }

    public function certificates(Request $r): Response
    {
        return $this->ok($this->svc()->certificates($this->user(), $r->intParam('id')));
    }

    public function createCertificate(Request $r): Response
    {
        return $this->created($this->svc()->createCertificate($this->user(), $r->intParam('id'), $r->body));
    }

    public function certify(Request $r): Response
    {
        $this->svc()->certify($this->user(), $r->intParam('id'), $r->body);
        return Response::noContent();
    }

    public function pay(Request $r): Response
    {
        return $this->created($this->svc()->recordPayment($this->user(), $r->intParam('id'), $r->body));
    }
}
