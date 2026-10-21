<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Services\ReportService;

final class ReportController extends BaseController
{
    public function export(Request $r): Response
    {
        $pid = $r->queryParam('project_id');
        return $this->c->get(ReportService::class)->export($this->user(), $r->param('type'), $pid ? (int) $pid : null, (string) $r->queryParam('format', 'pdf'));
    }

    public function types(Request $r): Response
    {
        $role = $this->user()->role;
        return $this->ok(array_keys(array_filter(ReportService::TYPES, static fn ($roles) => in_array($role, $roles, true))));
    }
}
