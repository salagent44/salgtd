<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function setup(Request $request)
    {
        $user = $request->user();

        $secret = $this->google2fa->generateSecretKey();

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ]);

        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            config('app.name', 'SalGTD'),
            $user->email,
            $secret,
        );

        // Generate QR code as inline SVG data URL
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($otpauthUrl);
        $qrDataUrl = 'data:image/svg+xml;base64,' . base64_encode($svg);

        return response()->json([
            'secret' => $secret,
            'qr_svg' => $qrDataUrl,
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['confirmed' => false, 'error' => 'No 2FA secret set up'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            return response()->json(['confirmed' => false, 'error' => 'Invalid code'], 422);
        }

        $user->update(['two_factor_confirmed_at' => now()]);

        return response()->json(['confirmed' => true]);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! \Hash::check($request->password, $user->password)) {
            return response()->json(['disabled' => false, 'error' => 'Incorrect password'], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['disabled' => true]);
    }

    public function status(Request $request)
    {
        return response()->json([
            'enabled' => $request->user()->hasTwoFactorEnabled(),
        ]);
    }
}
