<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Container;
use Lubie\Http\Request;
use Lubie\Http\RequestContext;
use Lubie\Http\Response;
use Lubie\Security\AuthUser;

/** Shared helpers for thin controllers: current user and JSON responses. */
abstract class BaseController
{
    public function __construct(protected Container $c)
    {
    }

    protected function ctx(): RequestContext
    {
        return $this->c->get(RequestContext::class);
    }

    protected function user(): AuthUser
    {
        return $this->ctx()->user();
    }

    protected function ok(mixed $data, int $status = 200): Response
    {
        return Response::json(['data' => $data], $status);
    }

    protected function created(int $id): Response
    {
        return Response::json(['data' => ['id' => $id]], 201);
    }

    protected function pageArgs(Request $r): array
    {
        return [max(1, (int) $r->queryParam('page', 1)), max(1, min(100, (int) $r->queryParam('per_page', 25)))];
    }
}
