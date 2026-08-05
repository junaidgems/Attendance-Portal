<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            $role = session('user_role');
            if (!$role) {
                Auth::logout();
                session()->flush();
                return redirect()->route('login')->with('error', 'Session expired! Please login again.');
            }
            if ($role == 'administrator') {
                return redirect()->route('admin.dashboard');
            } elseif ($role == 'Director') {
                return redirect()->route('director.dashboard');
            } elseif ($role == 'Manager') {
                return redirect()->route('manager.dashboard');
            } elseif ($role == 'line_manager') {
                return redirect()->route('line_manager.dashboard');
            } elseif ($role == 'employee') {
                return redirect()->route('employee.dashboard');
            } else {
                return redirect()->route('login');
            }
        }

        return view('attendence.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();

            $role = $user->role ? $user->role->name : 'login';
            
            if ($role == 'administrator') {
                return redirect()->route('admin.dashboard');
            } elseif ($role == 'Director') {
                return redirect()->route('director.dashboard');
            } elseif ($role == 'Manager') {
                return redirect()->route('manager.dashboard');
            } elseif ($role == 'Line_Manager') {
                return redirect()->route('line_manager.dashboard');
            } elseif ($role == 'employee') {
                return redirect()->route('employee.dashboard');
            } else {
                return redirect()->route('login');
            }


        }

        return back()->with('error', 'Invalid email or password!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Logged out successfully!')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}