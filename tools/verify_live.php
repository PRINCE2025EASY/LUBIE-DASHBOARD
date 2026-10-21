<?php

declare(strict_types=1);

/*
 * Live smoke test for a deployed dashboard (read-only, no login, no secrets).
 *   php tools/verify_live.php https://your-service.onrender.com          full check
 *   php tools/verify_live.php https://your-service.onrender.com --warm   pre-demo warm-up only
 * Optional session check: set LUBIE_CHECK_USER and LUBIE_CHECK_PASSWORD in the shell (demo
 * account only). The script logs in, waits, and checks the session is still valid.
 */
$base = rtrim((string) ($argv[1] ?? ''), '/');
if (!preg_match('#^https://#', $base)) {
    fwrite(STDERR, "Give the https:// address of the site.\n");
    exit(2);
}
$fails = 0;
$cookie = '';
function req(string $url, string $method = 'GET', ?string $body = null, array $headers = [], bool $follow = false): array
{
    global $cookie;
    $ch = curl_init($url);
    $h = array_merge(['Accept: application/json, text/html'], $headers);
    if ($cookie !== '') {
        $h[] = 'Cookie: ' . $cookie;
    }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_TIMEOUT => 90, CURLOPT_HTTPHEADER => $h]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $t = microtime(true);
    $raw = curl_exec($ch);
    $ms = (int) ((microtime(true) - $t) * 1000);
    if ($raw === false) {
        return ['code' => 0, 'headers' => '', 'body' => curl_error($ch), 'ms' => $ms];
    }
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersRaw = substr($raw, 0, $hs);
    if (preg_match_all('/^set-cookie:\s*([^=]+=[^;]*)/mi', $headersRaw, $m)) {
        $cookie = end($m[1]);
    }
    return ['code' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'headers' => $headersRaw, 'body' => substr($raw, $hs), 'ms' => $ms];
}
function check(string $label, bool $ok, string $detail = ''): void
{
    global $fails;
    $fails += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . ($detail !== '' ? "  ({$detail})" : '') . PHP_EOL;
}

// Warm-up: a sleeping free service takes about a minute to start.
$start = microtime(true);
do {
    $h = req($base . '/health');
    if ($h['code'] === 200) {
        break;
    }
    sleep(5);
} while (microtime(true) - $start < 180);
check('Service awake: /health', $h['code'] === 200 && str_contains($h['body'], '"db":"ok"'), sprintf('%d after %.0f s', $h['code'], microtime(true) - $start));
if (in_array('--warm', $argv, true)) {
    req($base . '/login');
    echo "Warm-up done. Open the site now.\n";
    exit($fails ? 1 : 0);
}

$http = req(preg_replace('#^https://#', 'http://', $base) . '/login');
check('HTTP redirects to HTTPS', in_array($http['code'], [301, 302, 307, 308], true) && stripos($http['headers'], 'location: https://') !== false, (string) $http['code']);
$login = req($base . '/login');
check('Login page loads', $login['code'] === 200, $login['ms'] . ' ms');
check('HSTS header', stripos($login['headers'], 'strict-transport-security') !== false);
check('Content-Security-Policy without inline script', (bool) preg_match("/content-security-policy:[^\n]*script-src 'self'/i", $login['headers']) && !preg_match("/content-security-policy:[^\n]*script-src[^;]*unsafe-inline/i", $login['headers']));
check('Secure __Host- session cookie', (bool) preg_match('/set-cookie:\s*__Host-lubie=[^\n]*secure[^\n]*httponly|set-cookie:\s*__Host-lubie=[^\n]*httponly[^\n]*secure/i', $login['headers']));
check('No PHP version disclosed', stripos($login['headers'], 'x-powered-by') === false);
foreach (['/.env', '/composer.json', '/src/Container.php', '/config/permissions.php', '/vendor/autoload.php', '/database/schema.sql', '/.git/config'] as $p) {
    $r = req($base . $p);
    check("Blocked: {$p}", in_array($r['code'], [403, 404], true) && !str_contains($r['body'], 'DB_PASSWORD'), (string) $r['code']);
}
$dir = req($base . '/assets/');
check('Directory listing off', !str_contains($dir['body'], 'Index of'), (string) $dir['code']);
$api = req($base . '/api/v1/projects');
check('API refuses anonymous access', $api['code'] === 401, (string) $api['code']);
$err = req($base . '/api/v1/projects/abc');
check('Errors reveal no internals (debug off)', !preg_match('/SQLSTATE|Stack trace|\.php:\d+|Fatal error/i', $err['body']), (string) $err['code']);

$user = getenv('LUBIE_CHECK_USER');
$pass = getenv('LUBIE_CHECK_PASSWORD');
if ($user && $pass) {
    $cookie = '';
    $page = req($base . '/login');
    preg_match('/name="csrf-token" content="([0-9a-f]+)"/', $page['body'], $m);
    $r = req($base . '/api/v1/auth/login', 'POST', json_encode(['username' => $user, 'password' => $pass]), ['Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')]);
    check('Demo login', $r['code'] === 200, (string) $r['code']);
    $me1 = req($base . '/api/v1/auth/me');
    $poll = req($base . '/api/v1/changes');
    check('Polling endpoint answers', $poll['code'] === 200, $poll['ms'] . ' ms');
    echo "Waiting 70 s to check the session survives (restart the service in Render now to test a restart)...\n";
    sleep(70);
    $me2 = req($base . '/api/v1/auth/me');
    check('Session still valid after the wait', $me1['code'] === 200 && $me2['code'] === 200, $me2['code'] . ', ' . $me2['ms'] . ' ms');
} else {
    echo "SKIP login, polling and session checks (set LUBIE_CHECK_USER and LUBIE_CHECK_PASSWORD to a demo account).\n";
}
echo $fails ? "{$fails} check(s) failed.\n" : "All checks passed.\n";
exit($fails ? 1 : 0);
