<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteVersion;

class NoteVersionController extends Controller
{
    public function index(Note $note)
    {
        return response()->json(
            $note->versions()->orderByDesc('created_at')->limit(50)->get()
        );
    }

    public function store(Note $note)
    {
        // Skip if content unchanged from latest version
        $latest = $note->versions()->latest('created_at')->first();
        if ($latest && $latest->content === $note->content && $latest->title === $note->title) {
            return response()->noContent();
        }

        $note->versions()->create([
            'title' => $note->title,
            'content' => $note->content,
        ]);

        // Prune old versions (keep last 50)
        $keepIds = $note->versions()->orderByDesc('created_at')->limit(50)->pluck('id');
        $note->versions()->whereNotIn('id', $keepIds)->delete();

        return response()->noContent();
    }

    public function restore(Note $note, NoteVersion $version)
    {
        abort_unless($version->note_id === $note->id, 403);

        // Save current state before restoring
        $note->versions()->create([
            'title' => $note->title,
            'content' => $note->content,
        ]);

        $note->update([
            'title' => $version->title,
            'content' => $version->content,
        ]);

        return back();
    }
}
