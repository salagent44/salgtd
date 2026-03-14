<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sal GTD - Two-Factor Authentication</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            background: oklch(0.96 0.025 60);
            background-image:
                radial-gradient(ellipse at 0% 0%, oklch(0.82 0.10 50 / 18%) 0%, transparent 50%),
                radial-gradient(ellipse at 100% 30%, oklch(0.78 0.12 25 / 12%) 0%, transparent 40%),
                radial-gradient(ellipse at 50% 100%, oklch(0.85 0.08 60 / 15%) 0%, transparent 40%);
            min-height: 100vh;
        }
        .brand-text { font-family: 'Caveat', cursive; }
        .login-card {
            background: oklch(0.98 0.018 55);
            box-shadow: 0 4px 24px oklch(0.55 0.08 40 / 12%), 0 1px 6px oklch(0.60 0.06 50 / 10%);
            border-radius: 1.5rem;
        }
        .login-input {
            background: oklch(0.97 0.015 60);
            border: 2px solid oklch(0.85 0.03 55);
            border-radius: 0.75rem;
            color: oklch(0.20 0.03 40);
            transition: all 0.2s;
            letter-spacing: 0.5em;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .login-input:focus {
            border-color: oklch(0.60 0.15 30);
            box-shadow: 0 0 0 3px oklch(0.60 0.15 30 / 12%);
            outline: none;
        }
        .login-btn {
            background: oklch(0.58 0.18 30);
            color: oklch(0.99 0.005 60);
            border-radius: 2rem;
            font-weight: 600;
            box-shadow: 0 3px 10px oklch(0.45 0.15 30 / 22%), inset 0 2px 0 oklch(1 0 0 / 15%);
            transition: all 0.2s;
        }
        .login-btn:hover { box-shadow: 0 4px 14px oklch(0.45 0.15 30 / 30%), inset 0 2px 0 oklch(1 0 0 / 15%); transform: translateY(-1px); }
        .login-btn:active { transform: translateY(0); }
        .sun-icon { color: oklch(0.70 0.15 55); }
        .tagline { color: oklch(0.48 0.025 45); }
        .error-text { color: oklch(0.50 0.22 15); }
    </style>
</head>
<body class="antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" style="background: oklch(0.92 0.06 50);">
                <svg class="sun-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h1 class="brand-text text-4xl font-bold" style="color: oklch(0.58 0.18 30);">Two-Factor Auth</h1>
            <p class="tagline mt-1 text-sm">Enter the code from your authenticator app</p>
        </div>

        <div class="login-card w-full max-w-md px-8 py-8">
            <form method="POST" action="{{ route('2fa.challenge') }}">
                @csrf

                <div>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        inputmode="numeric"
                        maxlength="6"
                        placeholder="000000"
                        class="login-input block w-full px-4 py-4"
                    />
                    @error('code')
                        <p class="error-text mt-2 text-xs text-center">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6">
                    <button type="submit" class="login-btn w-full py-3 text-sm tracking-wide">
                        Verify
                    </button>
                </div>
            </form>
        </div>

        <a href="{{ route('login') }}" class="tagline text-xs mt-6 hover:underline">Back to login</a>
    </div>
</body>
</html>
