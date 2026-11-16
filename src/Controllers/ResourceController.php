<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Services\ResourceService;

/** Labour, materials, equipment and material requests (FR-18, FR-19, FR-33). */
final class ResourceController extends BaseController
{
    private function svc(): ResourceService
    {
        return $this->c->get(ResourceService::class);
    }

    public function index(Request $r): Response
    {
        return $this->ok($this->svc()->overview($this->user(), $r->intParam('id')));
    }

    public function store(Request $r): Response
    {
        return $this->created($this->svc()->create($this->user(), $r->intParam('id'), $r->body));
    }

    public function update(Request $r): Response
    {
        $this->svc()->update($this->user(), $r->intParam('id'), $r->body);
        return Response::noContent();
    }

    public function history(Request $r): Response
    {
        return $this->ok($this->svc()->history($this->user(), $r->intParam('id')));
    }

    public function usage(Request $r): Response
    {
        $res = $this->svc()->recordUsage($this->user(), $r->intParam('id'), $r->body);
        return Response::json(['data' => $res], $res['replayed'] ? 200 : 201);
    }

    public function equipmentStatus(Request $r): Response
    {
        $this->svc()->setEquipmentStatus($this->user(), $r->intParam('id'), $r->body);
        return Response::noContent();
    }

    public function createRequest(Request $r): Response
    {
        return $this->created($this->svc()->createRequest($this->user(), $r->intParam('id'), $r->body));
    }

    public function approveRequest(Request $r): Response
    {
        $this->svc()->approveRequest($this->user(), $r->intParam('id'));
        return Response::noContent();
    }

    public function cancelRequest(Request $r): Response
    {
        $this->svc()->cancelRequest($this->user(), $r->intParam('id'), $r->input('reason'));
        return Response::noContent();
    }

    public function deliverRequest(Request $r): Response
    {
        $this->svc()->deliverRequest($this->user(), $r->intParam('id'), $r->body);
        return Response::noContent();
    }
}
