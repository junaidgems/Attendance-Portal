<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            $user = Auth::guard($guard)->user();

            // Active user with a valid role goes to their own dashboard; anyone else reaches the
            // login page, which logs them out.
            if ($user && $user->isActive() && $user->dashboardRoute()) {
                return redirect()->route($user->dashboardRoute());
            }
        }

        return $next($request);
    }
}
