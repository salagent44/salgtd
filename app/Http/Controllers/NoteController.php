<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NoteController extends Controller
{
    public function index()
    {
        return inertia('Dashboard', [
            'items' => \App\Models\Item::with(['tags', 'email'])->orderBy('sort_order')->get(),
            'contexts' => \App\Models\Context::orderBy('sort_order')->get(),
            'notes' => Note::with('tags')->orderByDesc('updated_at')->get(),
            'events' => \App\Models\CalendarEvent::orderBy('event_date')->get(),
            'theme' => \App\Models\Setting::get('theme', 'default'),
            'note_font' => \App\Models\Setting::get('note_font', 'system'),
            'note_font_css' => \App\Models\Setting::getNoteFontCss(),
            'last_review' => \App\Models\Setting::get('last_review'),
            'review_progress' => \App\Models\Setting::get('review_progress'),
            'email_address' => \App\Models\Setting::get('email_address'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:1000',
            'content' => 'nullable|string|max:100000',
        ]);

        Note::create([
            'id' => Str::ulid(),
            'title' => $validated['title'] ?? '',
            'content' => $validated['content'] ?? '',
        ]);

        return back();
    }

    public function update(Request $request, Note $note)
    {
        $validated = $request->validate([
            'title' => 'nullable|string',
            'content' => 'nullable|string',
        ]);

        // Convert nulls (from ConvertEmptyStringsToNull middleware) back to empty strings
        if (array_key_exists('title', $validated) && $validated['title'] === null) {
            $validated['title'] = '';
        }
        if (array_key_exists('content', $validated) && $validated['content'] === null) {
            $validated['content'] = '';
        }
        $note->update($validated);

        return back();
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return back();
    }

    public function togglePin(Note $note)
    {
        $note->update(['pinned' => !$note->pinned]);

        return back();
    }

    public function toggleLock(Note $note)
    {
        $note->update(['locked' => !$note->locked]);

        return back();
    }

    public function trash(Note $note)
    {
        $note->update(['trashed' => true]);

        return back();
    }

    public function restore(Note $note)
    {
        $note->update(['trashed' => false]);

        return back();
    }

    public function addTag(Request $request, Note $note)
    {
        $validated = $request->validate([
            'tag' => 'required|string|max:100',
        ]);

        $note->tags()->firstOrCreate(['tag' => $validated['tag']]);

        return back();
    }

    public function removeTag(Note $note, string $tag)
    {
        $note->tags()->where('tag', $tag)->delete();

        return back();
    }
}
