<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class UpdateController extends Controller
{
    public function status(): JsonResponse
    {
        $hash = trim(@file_get_contents(base_path('COMMIT_HASH')) ?: 'unknown');
        $status = Setting::get('update_status', 'idle');
        $triggeredAt = Setting::get('update_triggered_at');

        return response()->json([
            'commit' => $hash,
            'status' => $status,
            'triggered_at' => $triggeredAt,
        ]);
    }

    public function trigger(): JsonResponse
    {
        $current = Setting::get('update_status', 'idle');
        if ($current === 'triggered' || $current === 'updating') {
            return response()->json(['triggered' => false, 'reason' => 'Update already in progress']);
        }

        $now = date('c');
        Setting::set('update_status', 'triggered');
        Setting::set('update_triggered_at', $now);

        // Write file-based trigger for the host cron job
        if (is_dir('/data')) {
            file_put_contents('/data/update-trigger', $now);
        }

        return response()->json(['triggered' => true]);
    }
}
