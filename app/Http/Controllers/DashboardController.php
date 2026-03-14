<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Context;
use App\Models\Item;
use App\Models\Note;
use App\Models\Setting;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Promote tickler items whose date has arrived to inbox
        Item::where('status', 'tickler')
            ->whereNotNull('tickler_date')
            ->where('tickler_date', '<=', now()->toDateString())
            ->update(['status' => 'inbox', 'tickler_date' => null, 'context' => null]);

        return Inertia::render('Dashboard', [
            'items' => fn () => Item::with(['tags', 'email', 'checklistItems'])->orderBy('sort_order')->get(),
            'contexts' => fn () => Context::orderBy('sort_order')->get(),
            'notes' => Inertia::lazy(fn () => Note::with('tags')->orderByDesc('updated_at')->get()),
            'events' => Inertia::lazy(fn () => CalendarEvent::orderBy('event_date')->get()),
            'theme' => Setting::get('theme', 'default'),
            'note_font' => Setting::get('note_font', 'system'),
            'note_font_css' => Setting::getNoteFontCss(),
            'last_review' => fn () => Setting::get('last_review'),
            'review_progress' => fn () => Setting::get('review_progress'),
            'email_address' => Setting::get('email_address'),
            'commit_hash' => trim(@file_get_contents(base_path('COMMIT_HASH')) ?: 'unknown'),
        ]);
    }
}
