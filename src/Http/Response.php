<?php

declare(strict_types=1);

namespace Lubie\Http;

/** HTTP response. Body is a string, or a callable that streams output (exports, backups). */
final class Response
{
    /** @var array<string, string> */
    public array $headers = [];
    /** @var list<string> raw Set-Cookie header values */
    public array $cookies = [];

    /** @param string|callable(): void $body */
    public function __construct(public int $status = 200, public mixed $body = '', array $headers = [])
    {
        $this->headers = $headers;
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, '', ['Location' => $location, 'Cache-Control' => 'no-store']);
    }

    public static function noContent(): self
    {
        return new self(204, '', ['Cache-Control' => 'no-store']);
    }

    /** @param callable(): void $writer */
    public static function stream(callable $writer, string $contentType, string $filename): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'download';
        return new self(200, $writer, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function error(HttpException $e, string $requestId): self
    {
        $err = ['code' => $e->errorCode, 'message' => $e->getMessage(), 'request_id' => $requestId];
        if ($e->fields) {
            $err['fields'] = $e->fields;
        }
        return self::json(['error' => $err], $e->status);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** Output to the client (production only). */
    public function send(): void
    {
        header_remove('X-Powered-By');
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        foreach ($this->cookies as $c) {
            header('Set-Cookie: ' . $c, false);
        }
        if (is_callable($this->body)) {
            ($this->body)();
            return;
        }
        echo $this->body;
    }

    /** Collect streamed output into a string (tests). */
    public function bodyString(): string
    {
        if (is_callable($this->body)) {
            ob_start();
            ($this->body)();
            return (string) ob_get_clean();
        }
        return (string) $this->body;
    }
}
