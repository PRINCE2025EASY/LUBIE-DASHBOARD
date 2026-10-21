<?php

declare(strict_types=1);

namespace Lubie\Http;

/** Immutable view of an HTTP request. Built from globals in production, by hand in tests. */
final class Request
{
    /** @var array<string, string> route parameters set by the router */
    public array $params = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers lower-case names
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly string $ip = '127.0.0.1',
        public readonly string $rawBody = '',
    ) {
    }

    public static function fromGlobals(string $basePath): self
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        $path = '/' . trim($uri === '' ? '/' : $uri, '/');
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        $raw = (string) file_get_contents('php://input', false, null, 0, 400_000);
        $body = $_POST;
        if (str_contains($headers['content-type'] ?? '', 'application/json')) {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $_GET,
            $body,
            $headers,
            $_COOKIE,
            self::clientIp($headers, (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), \Lubie\Support\Env::int('TRUSTED_PROXY_HOPS', 0)),
            $raw,
        );
    }

    /**
     * Client IP behind a host's proxy (Render, Koyeb). With TRUSTED_PROXY_HOPS = n, take the n-th
     * X-Forwarded-For entry from the right: entries further left are client-supplied and can be faked.
     * 0 (default, XAMPP) uses REMOTE_ADDR only.
     * @param array<string, string> $headers
     */
    public static function clientIp(array $headers, string $remote, int $hops): string
    {
        if ($hops <= 0 || empty($headers['x-forwarded-for'])) {
            return $remote;
        }
        $list = array_values(array_filter(array_map('trim', explode(',', $headers['x-forwarded-for']))));
        $ip = $list[count($list) - $hops] ?? $list[0] ?? $remote;
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : $remote;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function param(string $key): string
    {
        return $this->params[$key] ?? '';
    }

    public function intParam(string $key): int
    {
        $v = $this->params[$key] ?? '';
        if (!ctype_digit($v)) {
            throw HttpException::notFound();
        }
        return (int) $v;
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    public function isStateChanging(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function userAgent(): string
    {
        return mb_substr($this->header('user-agent') ?? '', 0, 255);
    }
}
