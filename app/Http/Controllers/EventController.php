<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'event_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'event_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'recurrence' => 'nullable|in:yearly,monthly,weekly',
        ]);

        CalendarEvent::create([
            'id' => Str::ulid(),
            'title' => $validated['title'],
            'event_date' => $validated['event_date'],
            'end_date' => $validated['end_date'] ?? null,
            'event_time' => $validated['event_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'description' => $validated['description'] ?? '',
            'color' => $validated['color'] ?? 'blue',
            'recurrence' => $validated['recurrence'] ?? null,
        ]);

        return back();
    }

    public function update(Request $request, CalendarEvent $event)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'event_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'event_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'recurrence' => 'nullable|in:yearly,monthly,weekly',
        ]);

        if (array_key_exists('description', $validated) && $validated['description'] === null) {
            $validated['description'] = '';
        }
        $event->update($validated);

        return back();
    }

    public function destroy(CalendarEvent $event)
    {
        $event->delete();

        return back();
    }

    public function move(Request $request, CalendarEvent $event)
    {
        $validated = $request->validate([
            'event_date' => 'required|date',
        ]);

        // If multi-day, shift end_date by the same delta
        if ($event->end_date) {
            $oldStart = new \DateTime($event->event_date->format('Y-m-d'));
            $newStart = new \DateTime($validated['event_date']);
            $diff = $oldStart->diff($newStart);
            $newEnd = (new \DateTime($event->end_date->format('Y-m-d')));
            if ($newStart > $oldStart) {
                $newEnd->add($diff);
            } else {
                $newEnd->sub(new \DateInterval('P' . $diff->days . 'D'));
            }
            $validated['end_date'] = $newEnd->format('Y-m-d');
        }

        $event->update($validated);

        return back();
    }
}
