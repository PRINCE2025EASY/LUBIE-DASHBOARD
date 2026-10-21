<?php

declare(strict_types=1);

namespace Lubie\Http;

/**
 * Minimal router. Each route declares a permission (deny by default):
 *   'public'  no login needed
 *   'auth'    any logged-in user
 *   'perm:x'  user's role must hold permission x (config/permissions.php)
 */
final class Router
{
    /** @var list<array{method:string, regex:string, names:list<string>, handler:array{0:class-string,1:string}, guard:string}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler */
    public function add(string $method, string $pattern, array $handler, string $guard): void
    {
        $names = [];
        $regex = preg_replace_callback('/\{(\w+)\}/', static function (array $m) use (&$names): string {
            $names[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#', 'names' => $names, 'handler' => $handler, 'guard' => $guard];
    }

    /** @return array{handler:array{0:class-string,1:string}, guard:string}|null */
    public function match(Request $req): ?array
    {
        $pathMatched = false;
        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $req->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($r['method'] !== $req->method && !($r['method'] === 'GET' && $req->method === 'HEAD')) {
                continue;
            }
            array_shift($m);
            $req->params = array_combine($r['names'], array_map('rawurldecode', $m)) ?: [];
            return ['handler' => $r['handler'], 'guard' => $r['guard']];
        }
        if ($pathMatched) {
            throw new HttpException(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.');
        }
        return null;
    }
}
