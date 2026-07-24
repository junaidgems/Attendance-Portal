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
            if (Auth::guard($guard)->check()) {
                $role = session('user_role', 'employee');
                $routes = [
                    'administrator' => 'admin.dashboard',
                    'director' => 'director.dashboard',
                    'manager' => 'manager.dashboard',
                    'line_manager' => 'line_manager.dashboard',
                    'employee' => 'employee.dashboard',
                ];
                return redirect()->route($routes[$role] ?? 'employee.dashboard');
            }
        }

        return $next($request);
    }
}