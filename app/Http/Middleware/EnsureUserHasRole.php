<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the request only when the logged-in user has one of the given roles.
 *
 * Usage: ->middleware('role:administrator') or ->middleware('role:line_manager,manager')
 *
 * The role is read from the database on every request, so a role change or a deactivated
 * account takes effect immediately (not only after the next login).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your account is inactive. Please contact Admin.');
        }

        if (! $user->hasRole(...$roles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
