<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Services\ProgressService;
use Lubie\Services\SyncService;

final class ProgressController extends BaseController
{
    public function history(Request $r): Response
    {
        return $this->ok($this->c->get(ProgressService::class)->history($this->user(), $r->intParam('id')));
    }

    public function submit(Request $r): Response
    {
        $res = $this->c->get(ProgressService::class)->submit($this->user(), $r->intParam('id'), $r->body);
        return Response::json(['data' => $res], $res['replayed'] ? 200 : 201);
    }

    public function queue(Request $r): Response
    {
        return $this->ok($this->c->get(ProgressService::class)->queue($this->user(), $r->intParam('id')));
    }

    public function mine(Request $r): Response
    {
        return $this->ok($this->c->get(ProgressService::class)->mine($this->user(), $r->intParam('id')));
    }

    public function approve(Request $r): Response
    {
        $this->c->get(ProgressService::class)->approve($this->user(), $r->intParam('id'));
        return $this->ok(['status' => 'approved']);
    }

    public function returnEntry(Request $r): Response
    {
        $this->c->get(ProgressService::class)->returnEntry($this->user(), $r->intParam('id'), (string) $r->input('return_reason', ''));
        return $this->ok(['status' => 'returned']);
    }

    public function sync(Request $r): Response
    {
        $items = $r->input('items', []);
        return $this->ok($this->c->get(SyncService::class)->process($this->user(), is_array($items) ? array_values($items) : []));
    }
}
