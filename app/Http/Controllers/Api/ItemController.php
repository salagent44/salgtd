<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function process(Request $request, Item $item)
    {
        $validated = $request->validate([
            'status' => 'required|in:inbox,next-action,project,waiting,someday,tickler,done,trash',
            'title' => 'sometimes|string|max:500',
            'context' => 'nullable|string',
            'waiting_for' => 'nullable|string',
            'waiting_date' => 'nullable|date',
            'tickler_date' => 'nullable|date',
            'flagged' => 'sometimes|boolean',
            'goal' => 'nullable|string',
            'project_id' => 'nullable|string|exists:items,id',
        ]);

        // Track completion metadata
        if ($validated['status'] === 'done' && $item->status !== 'done') {
            $validated['original_status'] = $item->status;
            $validated['completed_at'] = now();
        } elseif ($validated['status'] !== 'done') {
            $validated['completed_at'] = null;
            $validated['original_status'] = null;
        }

        // When reclassifying FROM project to something else
        if ($item->status === 'project' && $validated['status'] !== 'project') {
            $validated['goal'] = null;
            Item::where('project_id', $item->id)->update(['project_id' => null]);
        }

        // When setting TO project: clear its own project_id
        if ($validated['status'] === 'project') {
            $validated['project_id'] = null;
        }

        // Clear fields that don't belong to the new status
        $newStatus = $validated['status'];
        if ($newStatus !== 'next-action') {
            $validated['context'] = $validated['context'] ?? null;
        }
        if ($newStatus !== 'waiting') {
            $validated['waiting_for'] = $validated['waiting_for'] ?? null;
            $validated['waiting_date'] = $validated['waiting_date'] ?? null;
        }
        if ($newStatus !== 'tickler') {
            $validated['tickler_date'] = $validated['tickler_date'] ?? null;
        }

        $item->update($validated);

        return response()->json([
            'item' => $item->fresh()->load(['tags', 'email']),
        ]);
    }

    public function moveToInbox(Item $item)
    {
        $item->update([
            'status' => 'inbox',
            'context' => null,
            'waiting_for' => null,
            'waiting_date' => null,
            'tickler_date' => null,
        ]);

        return response()->json([
            'item' => $item->fresh()->load(['tags', 'email']),
        ]);
    }
}
