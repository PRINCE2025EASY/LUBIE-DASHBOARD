<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Services\ThresholdService;

final class ThresholdController extends BaseController
{
    public function index(Request $r): Response
    {
        $pid = $r->queryParam('project_id');
        return $this->ok($this->c->get(ThresholdService::class)->effective($this->user(), $pid ? (int) $pid : null));
    }

    public function setGlobal(Request $r): Response
    {
        $this->c->get(ThresholdService::class)->setGlobal($this->user(), $r->param('key'), (string) $r->input('value', ''), $r->input('min_allowed'), $r->input('max_allowed'));
        return Response::noContent();
    }

    public function setProject(Request $r): Response
    {
        $v = $r->input('value');
        $this->c->get(ThresholdService::class)->setProject($this->user(), $r->intParam('id'), $r->param('key'), $v === null ? null : (string) $v);
        return Response::noContent();
    }

    public function propose(Request $r): Response
    {
        $pid = $r->input('project_id');
        return $this->created($this->c->get(ThresholdService::class)->propose($this->user(), $pid ? (int) $pid : null, (string) $r->input('setting_key', ''), (string) $r->input('value', ''), $r->input('justification')));
    }

    public function proposals(Request $r): Response
    {
        return $this->ok($this->c->get(ThresholdService::class)->proposals());
    }

    public function decide(Request $r): Response
    {
        $this->c->get(ThresholdService::class)->decide($this->user(), $r->intParam('id'), filter_var($r->input('approve', false), FILTER_VALIDATE_BOOL));
        return Response::noContent();
    }
}
