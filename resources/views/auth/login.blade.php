<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sal GTD - Login</title>
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
        .brand-text {
            font-family: 'Caveat', cursive;
        }
        .login-card {
            background: oklch(0.98 0.018 55);
            box-shadow:
                0 4px 24px oklch(0.55 0.08 40 / 12%),
                0 1px 6px oklch(0.60 0.06 50 / 10%);
            border-radius: 1.5rem;
        }
        .login-input {
            background: oklch(0.97 0.015 60);
            border: 2px solid oklch(0.85 0.03 55);
            border-radius: 0.75rem;
            color: oklch(0.20 0.03 40);
            transition: all 0.2s;
        }
        .login-input:focus {
            border-color: oklch(0.60 0.15 30);
            box-shadow: 0 0 0 3px oklch(0.60 0.15 30 / 12%);
            outline: none;
        }
        .login-input::placeholder {
            color: oklch(0.55 0.02 45);
        }
        .login-btn {
            background: oklch(0.58 0.18 30);
            color: oklch(0.99 0.005 60);
            border-radius: 2rem;
            font-weight: 600;
            box-shadow:
                0 3px 10px oklch(0.45 0.15 30 / 22%),
                inset 0 2px 0 oklch(1 0 0 / 15%);
            transition: all 0.2s;
        }
        .login-btn:hover {
            box-shadow:
                0 4px 14px oklch(0.45 0.15 30 / 30%),
                inset 0 2px 0 oklch(1 0 0 / 15%);
            transform: translateY(-1px);
        }
        .login-btn:active {
            transform: translateY(0);
        }
        .login-label {
            color: oklch(0.40 0.03 40);
            font-weight: 500;
        }
        .login-check {
            accent-color: oklch(0.58 0.18 30);
        }
        .error-text {
            color: oklch(0.50 0.22 15);
        }
        .sun-icon {
            color: oklch(0.70 0.15 55);
        }
        .tagline {
            color: oklch(0.48 0.025 45);
        }
    </style>
</head>
<body class="antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4">

        <!-- Logo & brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" style="background: oklch(0.92 0.06 50);">
                <svg class="sun-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/>
                    <line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/>
                    <line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
            </div>
            <h1 class="brand-text text-4xl font-bold" style="color: oklch(0.58 0.18 30);">Sal GTD</h1>
            <p class="tagline mt-1 text-sm">Get things done, beautifully.</p>
        </div>

        <!-- Login card -->
        <div class="login-card w-full max-w-md px-8 py-8">

            <!-- Session Status -->
            @if (session('status'))
                <div class="mb-4 text-sm font-medium" style="color: oklch(0.45 0.15 145);">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email -->
                <div>
                    <label for="email" class="login-label block text-sm mb-1.5">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="you@example.com"
                        class="login-input block w-full px-4 py-3 text-sm"
                    />
                    @error('email')
                        <p class="error-text mt-1.5 text-xs">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mt-5">
                    <label for="password" class="login-label block text-sm mb-1.5">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        class="login-input block w-full px-4 py-3 text-sm"
                    />
                    @error('password')
                        <p class="error-text mt-1.5 text-xs">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember me -->
                <div class="flex items-center mt-5">
                    <input id="remember_me" type="checkbox" name="remember" class="login-check w-4 h-4 rounded">
                    <label for="remember_me" class="ml-2 text-sm tagline select-none">Remember me</label>
                </div>

                <!-- Submit -->
                <div class="mt-6">
                    <button type="submit" class="login-btn w-full py-3 text-sm tracking-wide">
                        Sign in
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <p class="tagline text-xs mt-6">Sal GTD &mdash; your calm productivity companion</p>
    </div>
</body>
</html>
