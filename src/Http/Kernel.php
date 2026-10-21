<?php

declare(strict_types=1);

namespace Lubie\Http;

use Lubie\Audit\AuditLogger;
use Lubie\Container;
use Lubie\Db\DatabaseUnavailable;
use Lubie\Repositories\UserRepository;
use Lubie\Security\AuthUser;
use Lubie\Security\Authorizer;
use Lubie\Security\Csrf;
use Lubie\Security\Session;
use Lubie\Support\Env;
use Lubie\Views\View;
use Throwable;

/**
 * Request pipeline: security headers -> route -> session -> authentication ->
 * forced password change -> permission (deny by default) -> CSRF -> controller.
 * One central place maps errors to the single JSON error format or an HTML page.
 */
final class Kernel
{
    public function __construct(private Container $c, private Router $router, private string $basePath = '')
    {
    }

    public function handle(Request $req): Response
    {
        $requestId = bin2hex(random_bytes(8));
        $session = $this->c->get(Session::class);
        $audit = $this->c->get(AuditLogger::class);
        $audit->setRequestContext($req->ip, $req->userAgent());
        $user = null;
        try {
            $route = $this->router->match($req);
            if ($route === null) {
                throw HttpException::notFound('Page not found.');
            }
            $session->start($req->cookies[Session::cookieName()] ?? null);
            $user = $this->loadUser($session);
            $this->c->set(RequestContext::class, new RequestContext($req, $session, $user, $requestId, $this->basePath));
            $this->guard($route['guard'], $req, $session, $user);
            [$class, $method] = $route['handler'];
            $response = $this->c->get($class)->{$method}($req);
        } catch (HttpException $e) {
            $response = $this->renderError($req, $e, $requestId, $session);
        } catch (DatabaseUnavailable $e) {
            $response = $this->renderError($req, new HttpException(503, 'DB_UNAVAILABLE', $e->getMessage()), $requestId, $session)
                ->withHeader('Retry-After', '15');
        } catch (Throwable $e) {
            error_log(sprintf('[lubie %s] %s in %s:%d', $requestId, $e->getMessage(), $e->getFile(), $e->getLine()));
            $msg = Env::get('APP_ENV') === 'local' && Env::bool('APP_DEBUG', false) ? $e->getMessage() : 'Something went wrong. Your data was not saved. Reference: ' . $requestId;
            $response = $this->renderError($req, new HttpException(500, 'SERVER_ERROR', $msg), $requestId, $session);
        }
        $current = $this->currentUser() ?? $user;
        try {
            $session->save($current?->id, $req->ip, $req->userAgent());
        } catch (Throwable $e) {
            error_log('[lubie] session save failed: ' . $e->getMessage());
        }
        if ($session->id() !== '' && ($session->isDirty() || $session->isDestroyed())) {
            $response->cookies[] = $session->cookieHeader($this->basePath);
        }
        return SecurityHeaders::apply($response, $req);
    }

    private function currentUser(): ?AuthUser
    {
        try {
            return $this->c->get(RequestContext::class)->user;
        } catch (Throwable) {
            return null;
        }
    }

    private function loadUser(Session $session): ?AuthUser
    {
        $uid = $session->get('uid');
        if (!is_int($uid)) {
            return null;
        }
        $row = $this->c->get(UserRepository::class)->findActiveWithRole($uid);
        if ($row === null) {
            $session->destroy(); // deactivated or locked user: old session rejected (ST-10)
            return null;
        }
        return AuthUser::fromRow($row);
    }

    private function guard(string $guard, Request $req, Session $session, ?AuthUser $user): void
    {
        $parts = explode(',', $guard);
        $rule = $parts[0];
        $noCsrf = in_array('nocsrf', $parts, true);
        if ($rule !== 'public') {
            if ($user === null) {
                throw HttpException::unauthenticated();
            }
            $allowedDuringChange = in_array('pwchange', $parts, true);
            if ($user->mustChangePassword && !$allowedDuringChange) {
                throw new HttpException(403, 'PASSWORD_CHANGE_REQUIRED', 'Please change your password to continue.');
            }
            if (str_starts_with($rule, 'perm:')) {
                $this->c->get(Authorizer::class)->require($user, substr($rule, 5));
            }
        }
        if ($req->isStateChanging() && !$noCsrf && !Csrf::valid($session, $req->header('x-csrf-token') ?? (string) $req->input('_csrf', ''))) {
            throw new HttpException(403, 'CSRF_FAILED', 'Your page expired. Please reload and try again.');
        }
    }

    private function renderError(Request $req, HttpException $e, string $requestId, Session $session): Response
    {
        if ($req->isApi()) {
            return Response::error($e, $requestId);
        }
        if ($e->status === 401) {
            $next = $req->method === 'GET' ? '?next=' . rawurlencode($req->path) : '';
            return Response::redirect($this->basePath . '/login' . $next . ($session->wasExpired() ? ($next ? '&' : '?') . 'expired=1' : ''));
        }
        if ($e->errorCode === 'PASSWORD_CHANGE_REQUIRED') {
            return Response::redirect($this->basePath . '/password');
        }
        $view = $this->c->get(View::class);
        return Response::html($view->render('pages/error', ['title' => 'Error ' . $e->status, 'status' => $e->status, 'message' => $e->getMessage()]), $e->status);
    }
}
