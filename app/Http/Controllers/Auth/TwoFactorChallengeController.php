<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('2fa:user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $userId = $request->session()->get('2fa:user_id');
        $remember = $request->session()->get('2fa:remember', false);

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (! $user || ! $user->two_factor_secret) {
            return redirect()->route('login');
        }

        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);

        if (! $google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        // 2FA passed — log in
        $request->session()->forget(['2fa:user_id', '2fa:remember']);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
