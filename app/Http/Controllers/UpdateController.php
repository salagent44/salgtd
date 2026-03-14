<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class UpdateController extends Controller
{
    public function status(): JsonResponse
    {
        $currentCommit = trim(@file_get_contents(base_path('COMMIT_HASH')) ?: 'unknown');
        $buildReady = @file_get_contents('/data/build-ready');
        $pendingCommit = $buildReady ? trim($buildReady) : null;
        $applying = file_exists('/data/update-apply');

        return response()->json([
            'commit' => $currentCommit,
            'build_ready' => $pendingCommit && $pendingCommit !== $currentCommit,
            'pending_commit' => $pendingCommit,
            'applying' => $applying,
        ]);
    }

    public function apply(): JsonResponse
    {
        if (! file_exists('/data/build-ready')) {
            return response()->json(['applied' => false, 'reason' => 'No build ready']);
        }

        if (file_exists('/data/update-apply')) {
            return response()->json(['applied' => false, 'reason' => 'Already applying']);
        }

        file_put_contents('/data/update-apply', date('c'));

        return response()->json(['applied' => true]);
    }
}
