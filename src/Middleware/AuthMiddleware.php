<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Middleware;

use Closure;
use Devly\WP\Routing\Contracts\IRequest;
use Devly\WP\Routing\Responses\RedirectResponse;

class AuthMiddleware
{
    /** @return RedirectResponse|mixed */
    public function handle(IRequest $request, Closure $next)
    {
        if (! is_user_logged_in()) {
            return new RedirectResponse(wp_logout_url(home_url($request->wp()->request)), 401);
        }

        return $next($request);
    }
}
