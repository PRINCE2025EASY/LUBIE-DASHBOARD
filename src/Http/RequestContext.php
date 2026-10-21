<?php

declare(strict_types=1);

namespace Lubie\Http;

use Lubie\Security\AuthUser;
use Lubie\Security\Session;

/** Per-request state shared with controllers: request, session, current user. */
final class RequestContext
{
    public function __construct(
        public readonly Request $request,
        public readonly Session $session,
        public ?AuthUser $user,
        public readonly string $requestId,
        public readonly string $basePath,
    ) {
    }

    public function user(): AuthUser
    {
        if ($this->user === null) {
            throw HttpException::unauthenticated();
        }
        return $this->user;
    }
}
