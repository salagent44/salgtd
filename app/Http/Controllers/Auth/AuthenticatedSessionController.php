<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Check if user has 2FA enabled
        $user = Auth::user();
        if ($user->hasTwoFactorEnabled()) {
            // Log out and store user id in session for 2FA verification
            Auth::guard('web')->logout();
            $request->session()->put('2fa:user_id', $user->id);
            $request->session()->put('2fa:remember', $request->boolean('remember'));

            return redirect()->route('2fa.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
