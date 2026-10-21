<?php

declare(strict_types=1);

// Generates docs/API_REFERENCE.md from config/routes.php and config/permissions.php,
// so the reference cannot drift from the code. Run: php tools/gen_api_reference.php
require dirname(__DIR__) . '/vendor/autoload.php';

$router = new \Lubie\Http\Router();
(require dirname(__DIR__) . '/config/routes.php')($router);
$routes = array_map(static fn (array $r) => [$r['method'], $r['pattern'], $r['handler'], $r['guard']], $router->all());
$perms = require dirname(__DIR__) . '/config/permissions.php';
$names = ['admin' => 'Admin', 'director' => 'Director', 'pm' => 'PM', 'qs' => 'QS', 'site' => 'Site', 'client' => 'Client'];

$out = "# API reference\n\nGenerated from `config/routes.php` and `config/permissions.php` by `tools/gen_api_reference.php`. Do not edit by hand.\n\n"
    . "All API paths start with `/api/v1`. Requests and responses are JSON. State-changing requests need the `X-CSRF-Token` header from `GET /api/v1/auth/me`. "
    . "Errors use one format: `{\"error\": {\"code\", \"message\", \"request_id\", \"fields\"}}` with 401 (not logged in), 403 (role), 404 (unknown or out-of-scope ID), 409 (conflict), 422 (validation), 429 (throttled), 503 (database waking).\n\n"
    . "Project scope is checked inside the services: an ID outside the user's projects returns 404, never 403, so IDs cannot be probed.\n\n"
    . "| Method | Path | Handler | Who | Notes |\n|---|---|---|---|---|\n";
foreach ($routes as [$m, $p, $h, $g]) {
    $parts = explode(',', $g);
    $who = match (true) {
        $parts[0] === 'public' => 'Anyone',
        $parts[0] === 'auth' => 'Any logged-in user',
        default => implode(', ', array_map(fn ($r) => $names[$r], $perms[substr($parts[0], 5)] ?? [])) . ' (`' . substr($parts[0], 5) . '`)',
    };
    $notes = [];
    if (in_array('pwchange', $parts, true)) { $notes[] = 'allowed during forced password change'; }
    if (in_array('nocsrf', $parts, true)) { $notes[] = 'Bearer BACKUP_TOKEN, no session'; }
    $short = substr(strrchr($h[0], '\\'), 1) . '::' . $h[1];
    $out .= "| {$m} | `" . str_replace('$v', '/api/v1', $p) . "` | {$short} | {$who} | " . implode('; ', $notes) . " |\n";
}
$out .= "\n" . count($routes) . " routes.\n";
file_put_contents(dirname(__DIR__) . '/docs/API_REFERENCE.md', $out);
echo count($routes), " routes written\n";
