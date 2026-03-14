<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Context;
use App\Models\Item;
use App\Models\Note;
use App\Models\Setting;

class CalendarController extends Controller
{
    public function index()
    {
        return inertia('Dashboard', [
            'items' => Item::with(['tags', 'email'])->orderBy('sort_order')->get(),
            'contexts' => Context::orderBy('sort_order')->get(),
            'notes' => Note::with('tags')->orderByDesc('updated_at')->get(),
            'events' => CalendarEvent::orderBy('event_date')->get(),
            'theme' => Setting::get('theme', 'default'),
            'note_font' => Setting::get('note_font', 'system'),
            'note_font_css' => Setting::getNoteFontCss(),
            'last_review' => Setting::get('last_review'),
            'review_progress' => Setting::get('review_progress'),
            'email_address' => Setting::get('email_address'),
        ]);
    }
}
