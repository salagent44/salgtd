<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'sometimes|string',
            'totp_code' => 'sometimes|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check 2FA if enabled
        if ($user->hasTwoFactorEnabled()) {
            if (! $request->totp_code) {
                return response()->json([
                    'requires_2fa' => true,
                    'message' => 'Two-factor authentication code required.',
                ], 403);
            }

            $google2fa = new Google2FA();
            $secret = decrypt($user->two_factor_secret);

            if (! $google2fa->verifyKey($secret, $request->totp_code)) {
                throw ValidationException::withMessages([
                    'totp_code' => ['Invalid authentication code.'],
                ]);
            }
        }

        // Revoke existing tokens for this device
        $deviceName = $request->device_name ?? 'ios';
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
