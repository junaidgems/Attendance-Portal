<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Login as": Admin opens an employee's account (with full access) and returns to their own account with
 * one click. The Admin's id is kept in the session; every switch is written to the audit log.
 */
class ImpersonationController extends Controller
{
    /**
     * Session key holding the id of the Admin who is using someone else's account.
     */
    public const SESSION_KEY = 'impersonator_id';

    public function start(Request $request, $id): RedirectResponse
    {
        $admin = $request->user();
        $employee = Employee::with('user.role')->findOrFail($id);
        $user = $employee->user;

        if ($request->session()->has(self::SESSION_KEY)) {
            return redirect()->back()->with('error', 'Return to your own account before logging in as someone else.');
        }

        if (! $user) {
            return redirect()->back()->with('error', "{$employee->full_name} has no login account.");
        }

        if ($user->id === $admin->id || $user->hasRole('administrator')) {
            return redirect()->back()->with('error', 'You cannot log in as an Admin account.');
        }

        if (! $user->isActive() || $employee->employment_status !== 'active') {
            return redirect()->back()->with('error', "{$employee->full_name}'s account is inactive.");
        }

        if (! $user->dashboardRoute()) {
            return redirect()->back()->with('error', "{$employee->full_name}'s account has no portal role.");
        }

        AuditLog::record('security', 'Logged in as employee', "{$admin->name} opened the account of {$employee->full_name} ({$employee->employee_id})");

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $admin->id);

        return redirect()->route($user->dashboardRoute())
            ->with('info', "You are now using {$employee->full_name}'s account. Use \"Back to Admin\" at the top to return.");
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull(self::SESSION_KEY);
        $admin = $adminId ? User::find($adminId) : null;

        if (! $admin || ! $admin->hasRole('administrator') || ! $admin->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Please log in again.');
        }

        $previous = $request->user();

        Auth::login($admin);
        $request->session()->regenerate();

        AuditLog::record('security', 'Returned from employee account', "{$admin->name} left the account of ".($previous->name ?? 'an employee'));

        return redirect()->route('admin.employee')->with('success', 'You are back in your Admin account.');
    }
}
