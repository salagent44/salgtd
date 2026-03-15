<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function update(Request $request, string $key)
    {
        $allowed = ['theme', 'note_font', 'last_review', 'review_progress', 'email_address'];
        if (!in_array($key, $allowed)) {
            abort(403);
        }

        $validated = $request->validate([
            'value' => 'nullable|string',
        ]);

        // Validate theme values against allowlist
        if ($key === 'theme') {
            $allowedThemes = ['default', 'dark', 'theme-ocean', 'theme-forest', 'theme-midnight', 'theme-sunset', 'theme-slate', 'theme-obsidian', 'theme-gruvbox', 'theme-everforest', 'theme-rosepine', 'theme-things'];
            if (!in_array($validated['value'], $allowedThemes)) {
                abort(403);
            }
        }

        Setting::set($key, $validated['value']);

        return back();
    }
}
