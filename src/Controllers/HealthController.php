<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Db\Database;
use Lubie\Http\Request;
use Lubie\Http\Response;

/** /health: liveness and DB reachability only. Exposes no versions, paths or secrets. */
final class HealthController extends BaseController
{
    public function health(Request $r): Response
    {
        try {
            $this->c->get(Database::class)->value('SELECT 1');
            return Response::json(['status' => 'ok', 'db' => 'ok']);
        } catch (\Throwable) {
            return Response::json(['status' => 'degraded', 'db' => 'unavailable'], 503);
        }
    }
}
