<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class UpdateController extends Controller
{
    public function status(): JsonResponse
    {
        $hash = trim(@file_get_contents(base_path('COMMIT_HASH')) ?: 'unknown');

        // Check if an update is in progress
        $updating = file_exists('/data/update-trigger');

        // Check last update result
        $statusFile = '/data/update-status';
        $lastStatus = null;
        if (file_exists($statusFile)) {
            $lastStatus = trim(file_get_contents($statusFile));
        }

        return response()->json([
            'commit' => $hash,
            'updating' => $updating,
            'last_status' => $lastStatus,
        ]);
    }

    public function trigger(): JsonResponse
    {
        file_put_contents('/data/update-trigger', date('c'));

        return response()->json(['triggered' => true]);
    }
}
