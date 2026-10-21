<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Http\HttpException;
use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Repositories\SettingsRepository;
use Lubie\Repositories\UserRepository;
use Lubie\Security\Authorizer;
use Lubie\Security\Csrf;
use Lubie\Services\AuthService;
use Lubie\Support\Env;
use Lubie\Views\View;

/** Unified login, logout, password change, central role redirect (FR-01, FR-02, FR-29). */
final class AuthController extends BaseController
{
    public function home(Request $r): Response
    {
        $ctx = $this->ctx();
        if ($ctx->user === null) {
            return Response::redirect($ctx->basePath . '/login');
        }
        return Response::redirect($ctx->basePath . ($ctx->user->mustChangePassword ? '/password' : $ctx->user->dashboardPath));
    }

    public function loginPage(Request $r): Response
    {
        $ctx = $this->ctx();
        if ($ctx->user !== null) {
            return Response::redirect($ctx->basePath . $ctx->user->dashboardPath);
        }
        Csrf::token($ctx->session);
        return Response::html($this->c->get(View::class)->render('pages/login', [
            'title' => 'Log in', 'expired' => $r->queryParam('expired') === '1', 'next' => (string) $r->queryParam('next', ''),
        ], 'layout_public'));
    }

    public function login(Request $r): Response
    {
        $ctx = $this->ctx();
        $user = $this->c->get(AuthService::class)->login((string) $r->input('username', ''), (string) $r->input('password', ''), $r->ip);
        $ctx->session->regenerate();
        $ctx->session->set('uid', $user->id);
        $ctx->session->set('login_at', time());
        $ctx->user = $user;
        $csrf = Csrf::rotate($ctx->session);
        $target = $user->mustChangePassword ? '/password' : $this->safeNext((string) $r->input('next', ''), $user) ?? $user->dashboardPath;
        return Response::json(['data' => ['redirect' => $ctx->basePath . $target, 'csrf' => $csrf, 'role' => $user->role]]);
    }

    public function logout(Request $r): Response
    {
        $ctx = $this->ctx();
        if ($ctx->user !== null) {
            $this->c->get(\Lubie\Audit\AuditLogger::class)->log($ctx->user->id, 'logout', 'user', (string) $ctx->user->id);
        }
        $ctx->session->destroy();
        $ctx->user = null;
        return Response::json(['data' => ['redirect' => $ctx->basePath . '/login']]);
    }

    public function me(Request $r): Response
    {
        $u = $this->user();
        $authz = $this->c->get(Authorizer::class);
        $offline = $this->c->get(SettingsRepository::class)->bool('offline_enabled');
        $perms = array_keys(array_filter($authz->matrix(), static fn ($roles) => in_array($u->role, $roles, true)));
        return $this->ok(['id' => $u->id, 'username' => $u->username, 'full_name' => $u->fullName, 'role' => $u->role,
            'dashboard' => $u->dashboardPath, 'theme' => $u->theme, 'must_change_password' => $u->mustChangePassword,
            'permissions' => $perms, 'csrf' => Csrf::token($this->ctx()->session),
            'refresh_seconds' => max(15, $this->c->get(SettingsRepository::class)->int('refresh_interval_seconds', 60)),
            'offline_enabled' => $offline,
            'outbox_key' => $offline && $authz->can($u, 'sync.submit') ? self::outboxKey($u->id) : null]);
    }

    /**
     * Per-user key for the encrypted offline outbox (IndexedDB, AES-GCM). Derived from APP_KEY,
     * so it is stable across sessions (queued items survive a re-login) and differs per user.
     * Sent only over the authenticated session; never stored by the server.
     */
    private static function outboxKey(int $userId): ?string
    {
        $appKey = Env::get('APP_KEY', '');
        if ($appKey === '' || $appKey === null) {
            return null;
        }
        return base64_encode(hash_hmac('sha256', 'outbox:' . $userId, (string) $appKey, true));
    }

    public function passwordPage(Request $r): Response
    {
        return Response::html($this->c->get(View::class)->render('pages/password', ['title' => 'Change password']));
    }

    public function changePassword(Request $r): Response
    {
        $u = $this->user();
        $new = (string) $r->input('new_password', '');
        if ($new !== (string) $r->input('confirm_password', '')) {
            throw HttpException::validation(['confirm_password' => 'The two new passwords do not match.']);
        }
        $this->c->get(AuthService::class)->changePassword($u, (string) $r->input('current_password', ''), $new);
        $ctx = $this->ctx();
        $ctx->session->regenerate();
        $ctx->user = \Lubie\Security\AuthUser::fromRow($this->c->get(UserRepository::class)->findActiveWithRole($u->id) ?? throw HttpException::unauthenticated());
        return Response::json(['data' => ['redirect' => $ctx->basePath . $ctx->user->dashboardPath, 'csrf' => Csrf::rotate($ctx->session)]]);
    }

    public function theme(Request $r): Response
    {
        $t = (string) $r->input('theme', 'system');
        if (!in_array($t, ['light', 'dark', 'system'], true)) {
            throw HttpException::validation(['theme' => 'Choose light, dark or system.']);
        }
        $this->c->get(UserRepository::class)->update($this->user()->id, ['theme_pref' => $t]);
        return Response::noContent();
    }

    /** Return-to page only if it is a same-site path the role may view (FR-29). */
    private function safeNext(string $next, \Lubie\Security\AuthUser $u): ?string
    {
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '\\') || str_starts_with($next, '/api/')) {
            return null;
        }
        $first = '/' . explode('/', trim($next, '/'))[0];
        return $first === $u->dashboardPath ? $next : null;
    }
}
