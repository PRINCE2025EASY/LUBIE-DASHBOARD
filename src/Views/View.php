<?php

declare(strict_types=1);

namespace Lubie\Views;

use Lubie\Container;
use Lubie\Http\RequestContext;
use Lubie\Security\Csrf;
use Throwable;

/** Plain PHP templates with automatic helpers. All dynamic text goes through e(). */
final class View
{
    private string $dir;
    private const ASSET_VERSION = '0.3.0';

    public function __construct(private Container $c)
    {
        $this->dir = dirname(__DIR__, 2) . '/templates/';
    }

    /** @param array<string, mixed> $vars */
    public function render(string $template, array $vars = [], ?string $layout = 'layout'): string
    {
        $ctx = null;
        try {
            $ctx = $this->c->get(RequestContext::class);
        } catch (Throwable) {
        }
        $base = $ctx?->basePath ?? '';
        $helpers = [
            'e' => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'url' => static fn (string $p): string => $base . $p,
            'asset' => static fn (string $p): string => $base . '/assets/' . ltrim($p, '/') . '?v=' . self::ASSET_VERSION,
            'user' => $ctx?->user,
            'csrf' => $ctx !== null ? Csrf::token($ctx->session) : '',
            'basePath' => $base,
        ];
        $content = $this->capture($this->dir . $template . '.php', $vars + $helpers);
        if ($layout === null) {
            return $content;
        }
        return $this->capture($this->dir . $layout . '.php', $vars + $helpers + ['content' => $content]);
    }

    /** @param array<string, mixed> $vars */
    private function capture(string $file, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
