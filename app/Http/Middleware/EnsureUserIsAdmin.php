<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Keep member accounts out of the administrative console.
     *
     * Members have no dashboard of their own, so page visits send them back
     * to their NFC profile instead of showing a hard error, while any other
     * request method is rejected outright.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isMember()) {
            return $next($request);
        }

        if ($request->isMethod('GET') && $user->member !== null) {
            return redirect()->route('profile.show', $user->member);
        }

        abort(403);
    }
}
