<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Routes a user may still reach while their temporary password is
     * pending; anything else redirects to the change-password screen.
     *
     * @var array<int, string>
     */
    private const ALLOWED_ROUTES = [
        'temporary-password.edit',
        'temporary-password.update',
        'logout',
    ];

    /**
     * Force members to replace the administrator-set temporary password on
     * their first login before they can do anything else.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('temporary-password.edit');
    }
}
