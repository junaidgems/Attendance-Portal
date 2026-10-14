<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return $this->redirectForUser($this->logoutIfBlocked(request()) ?? Auth::user());
        }

        return view('attendence.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (! Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return back()->withInput($request->only('email'))->with('error', 'Invalid email or password!');
        }

        // New session id after login (prevents session fixation)
        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user->isActive()) {
            $this->endSession($request);

            return back()->withInput($request->only('email'))->with('error', 'Your account is inactive. Please contact Admin.');
        }

        if (! $user->dashboardRoute()) {
            $this->endSession($request);

            return back()->withInput($request->only('email'))->with('error', 'Your account has no valid role. Please contact Admin.');
        }

        // Kept for older views that read it; access control uses the database role
        session(['user_role' => $user->roleKey()]);

        return $this->redirectForUser($user);
    }

    public function logout(Request $request)
    {
        $this->endSession($request);

        return redirect()->route('login')
            ->with('success', 'Logged out successfully!')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    private function redirectForUser($user): RedirectResponse
    {
        return $user && $user->dashboardRoute()
            ? redirect()->route($user->dashboardRoute())
            : redirect()->route('login');
    }

    /**
     * Logged-in user who is inactive or has no valid role is logged out; returns null in that case.
     */
    private function logoutIfBlocked(Request $request)
    {
        $user = Auth::user();

        if ($user->isActive() && $user->dashboardRoute()) {
            return $user;
        }

        $this->endSession($request);

        return null;
    }

    private function endSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
