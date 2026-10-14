<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Admin's own profile: name / email and password of the login account.
 */
class ProfileController extends Controller
{
    public function index(): View
    {
        return view('attendence.pages.admin.profile');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update(['name' => $request->name, 'email' => $request->email]);

        // Keep the employee record (if the Admin has one) on the same email
        $user->employee?->update(['email' => $request->email]);

        AuditLog::record('profile', 'Profile updated', "{$user->name} updated their name / email", $user->name);

        return redirect()->route('admin.profile')->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.confirmed' => 'The new password and its confirmation do not match.',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return redirect()->route('admin.profile')->with('error', 'The current password is incorrect.');
        }

        $user->update(['password' => Hash::make($request->password)]);

        AuditLog::record('profile', 'Password changed', "{$user->name} changed their password", $user->name);

        return redirect()->route('admin.profile')->with('success', 'Password changed.');
    }
}
