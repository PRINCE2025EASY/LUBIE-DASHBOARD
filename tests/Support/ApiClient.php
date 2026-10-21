<?php

declare(strict_types=1);

namespace Lubie\Tests\Support;

use Lubie\Db\Database;
use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Security\Session;

/**
 * In-process HTTP client: builds a Request, runs the real Kernel (router, guards, CSRF,
 * sessions, controllers) and keeps the session cookie like a browser. No web server needed.
 */
final class ApiClient
{
    private array $cookies = [];
    public ?string $csrf = null;
    public Response $last;

    public function __construct(private Database $db, private string $ip = '10.0.0.1')
    {
    }

    /** @return array{status:int, json:mixed, body:string, headers:array<string,string>} */
    public function call(string $method, string $path, ?array $body = null, array $headers = [], bool $withCsrf = true): array
    {
        $parts = parse_url($path);
        parse_str($parts['query'] ?? '', $query);
        $h = ['accept' => str_starts_with($parts['path'], '/api/') ? 'application/json' : 'text/html'] + array_change_key_case($headers);
        if ($withCsrf && $this->csrf !== null && $method !== 'GET') {
            $h['x-csrf-token'] = $this->csrf;
        }
        if ($body !== null) {
            $h['content-type'] = 'application/json';
        }
        $req = new Request($method, $parts['path'], $query, $body ?? [], $h, $this->cookies, $this->ip, $body !== null ? (string) json_encode($body) : '');
        $c = lubie_container($this->db);
        $res = lubie_kernel($c)->handle($req);
        $this->last = $res;
        foreach ($res->cookies as $cookie) {
            if (preg_match('/^([^=]+)=([^;]*)/', $cookie, $m)) {
                if ($m[2] === '' || str_contains(strtolower($cookie), 'max-age=0') || str_contains($cookie, 'expires=Thu, 01 Jan 1970')) {
                    unset($this->cookies[$m[1]]);
                } else {
                    $this->cookies[$m[1]] = $m[2];
                }
            }
        }
        $bodyStr = $res->bodyString();
        $json = json_decode($bodyStr, true);
        if (is_array($json) && isset($json['data']['csrf'])) {
            $this->csrf = $json['data']['csrf'];
        }
        return ['status' => $res->status, 'json' => $json, 'body' => $bodyStr, 'headers' => $res->headers];
    }

    public function get(string $path): array
    {
        return $this->call('GET', $path);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->call('POST', $path, $body);
    }

    /** Load the login page (sets session + CSRF token), then log in. */
    public function login(string $username, string $password): array
    {
        $page = $this->call('GET', '/login');
        if (preg_match('/name="csrf-token" content="([0-9a-f]+)"/', $page['body'], $m)) {
            $this->csrf = $m[1];
        }
        return $this->post('/api/v1/auth/login', ['username' => $username, 'password' => $password]);
    }

    public function sessionId(): ?string
    {
        return $this->cookies[Session::cookieName()] ?? null;
    }

    public function dropCookies(): void
    {
        $this->cookies = [];
    }
}
