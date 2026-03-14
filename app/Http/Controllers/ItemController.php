<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'status' => 'sometimes|in:inbox,next-action,project,checklist,waiting,someday,tickler,done,trash',
            'context' => 'nullable|string',
            'waiting_for' => 'nullable|string',
            'waiting_date' => 'nullable|date',
            'goal' => 'nullable|string',
            'project_id' => 'nullable|string|exists:items,id',
        ]);

        Item::create([
            'id' => Str::ulid(),
            'title' => $validated['title'],
            'status' => $validated['status'] ?? 'inbox',
            'context' => $validated['context'] ?? null,
            'waiting_for' => $validated['waiting_for'] ?? null,
            'waiting_date' => $validated['waiting_date'] ?? null,
            'goal' => $validated['goal'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
        ]);

        return back();
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'status' => 'sometimes|in:inbox,next-action,project,checklist,waiting,someday,tickler,done,trash',
            'context' => 'nullable|string',
            'waiting_for' => 'nullable|string',
            'waiting_date' => 'nullable|date',
            'tickler_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'sort_order' => 'sometimes|integer',
            'flagged' => 'sometimes|boolean',
            'goal' => 'nullable|string',
            'project_id' => 'nullable|string|exists:items,id',
        ]);

        $item->update($validated);

        return back();
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return back();
    }

    public function process(Request $request, Item $item)
    {
        $validated = $request->validate([
            'status' => 'required|in:inbox,next-action,project,checklist,waiting,someday,tickler,done,trash',
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

        // When reclassifying FROM project to something else: clear goal, unlink tasks
        if ($item->status === 'project' && $validated['status'] !== 'project') {
            $validated['goal'] = null;
            Item::where('project_id', $item->id)->update(['project_id' => null]);
        }

        // When setting TO project: clear its own project_id (no nesting)
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

        return back();
    }

    public function moveToInbox(Item $item)
    {
        // If reclassifying from project, unlink all tasks
        if ($item->status === 'project') {
            Item::where('project_id', $item->id)->update(['project_id' => null]);
        }

        $item->update([
            'status' => 'inbox',
            'context' => null,
            'waiting_for' => null,
            'waiting_date' => null,
            'tickler_date' => null,
            'project_id' => null,
            'goal' => null,
            'flagged' => false,
            'completed_at' => null,
            'original_status' => null,
        ]);

        return back();
    }

    public function bulkProcess(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required',
            'status' => 'required|in:inbox,next-action,project,checklist,waiting,someday,tickler,done,trash',
        ]);

        $items = Item::whereIn('id', $validated['ids'])->get();

        foreach ($items as $item) {
            $data = ['status' => $validated['status']];

            if ($validated['status'] === 'done' && $item->status !== 'done') {
                $data['original_status'] = $item->status;
                $data['completed_at'] = now();
            } elseif ($validated['status'] !== 'done') {
                $data['completed_at'] = null;
                $data['original_status'] = null;
            }

            // Clear fields when moving to inbox
            if ($validated['status'] === 'inbox') {
                $data['context'] = null;
                $data['waiting_for'] = null;
                $data['waiting_date'] = null;
                $data['tickler_date'] = null;
            }

            $item->update($data);
        }

        return back();
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required',
        ]);

        Item::whereIn('id', $validated['ids'])->delete();

        return back();
    }

    public function scheduleEvent(Request $request, Item $item)
    {
        $validated = $request->validate([
            'event_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'event_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'color' => 'nullable|string',
            'recurrence' => 'nullable|in:yearly,monthly,weekly',
        ]);

        CalendarEvent::create([
            'id' => Str::ulid(),
            'title' => $item->title,
            'event_date' => $validated['event_date'],
            'end_date' => $validated['end_date'] ?? null,
            'event_time' => $validated['event_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'color' => $validated['color'] ?? 'blue',
            'recurrence' => $validated['recurrence'] ?? null,
        ]);

        $item->update([
            'status' => 'done',
            'completed_at' => now(),
            'original_status' => $item->status,
        ]);

        return back();
    }

    public function assignProject(Request $request, Item $item)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|string|exists:items,id',
        ]);

        $item->update(['project_id' => $validated['project_id']]);

        return back();
    }

    public function addTag(Request $request, Item $item)
    {
        $validated = $request->validate([
            'tag' => 'required|string|max:100',
        ]);

        $item->tags()->firstOrCreate(['tag' => $validated['tag']]);

        return back();
    }

    public function removeTag(Item $item, string $tag)
    {
        $item->tags()->where('tag', $tag)->delete();

        return back();
    }
}
