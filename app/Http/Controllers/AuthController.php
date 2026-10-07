<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the login view.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $demoUsers = User::with('roles')->get();

        return view('auth.login', compact('demoUsers'));
    }

    protected function redirectForUser(User $user): RedirectResponse
    {
        if ($user->hasRole('kitchen')) {
            return redirect()->route('kitchen.view')
                ->with('success', 'Logged in to Kitchen Display System (KDS).');
        }
        if ($user->hasRole('customer_display')) {
            return redirect()->route('kiosk.view');
        }
        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome back, ' . $user->name . '!');
    }

    /**
     * Handle regular login request.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return $this->redirectForUser(Auth::user());
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Quick 1-click switch login for demo / testing.
     */
    public function quickLogin(Request $request, string $email): RedirectResponse
    {
        $user = User::where('email', $email)->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectForUser($user);
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'You have been logged out.');
    }
}
