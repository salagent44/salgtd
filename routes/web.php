<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\InboundEmailController;
use App\Http\Controllers\SmtpStatusController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NoteVersionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Pages
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notes', [NoteController::class, 'index']);
    Route::get('/calendar', [CalendarController::class, 'index']);

    // Items API (bulk routes first to avoid {item} matching)
    Route::post('/items/bulk-process', [ItemController::class, 'bulkProcess']);
    Route::post('/items/bulk-delete', [ItemController::class, 'bulkDelete']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::put('/items/{item}', [ItemController::class, 'update']);
    Route::delete('/items/{item}', [ItemController::class, 'destroy']);
    Route::post('/items/{item}/process', [ItemController::class, 'process']);
    Route::post('/items/{item}/move-to-inbox', [ItemController::class, 'moveToInbox']);
    Route::post('/items/{item}/schedule-event', [ItemController::class, 'scheduleEvent']);
    Route::post('/items/{item}/assign-project', [ItemController::class, 'assignProject']);
    Route::post('/items/{item}/tags', [ItemController::class, 'addTag']);
    Route::delete('/items/{item}/tags/{tag}', [ItemController::class, 'removeTag']);

    // Checklist Items API
    Route::post('/items/{item}/checklist', [ChecklistItemController::class, 'store']);
    Route::put('/checklist-items/{checklistItem}', [ChecklistItemController::class, 'update']);
    Route::delete('/checklist-items/{checklistItem}', [ChecklistItemController::class, 'destroy']);
    Route::post('/checklist-items/{checklistItem}/toggle', [ChecklistItemController::class, 'toggle']);
    Route::post('/items/{item}/checklist/reorder', [ChecklistItemController::class, 'reorder']);

    // Notes API
    Route::post('/notes', [NoteController::class, 'store']);
    Route::put('/notes/{note}', [NoteController::class, 'update']);
    Route::delete('/notes/{note}', [NoteController::class, 'destroy']);
    Route::put('/notes/{note}/toggle-pin', [NoteController::class, 'togglePin']);
    Route::put('/notes/{note}/toggle-lock', [NoteController::class, 'toggleLock']);
    Route::put('/notes/{note}/trash', [NoteController::class, 'trash']);
    Route::put('/notes/{note}/restore', [NoteController::class, 'restore']);
    Route::post('/notes/{note}/tags', [NoteController::class, 'addTag']);
    Route::delete('/notes/{note}/tags/{tag}', [NoteController::class, 'removeTag']);

    // Note Versions API
    Route::get('/notes/{note}/versions', [NoteVersionController::class, 'index']);
    Route::post('/notes/{note}/versions', [NoteVersionController::class, 'store']);
    Route::post('/notes/{note}/versions/{version}/restore', [NoteVersionController::class, 'restore']);

    // Calendar Events API
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{event}', [EventController::class, 'update']);
    Route::delete('/events/{event}', [EventController::class, 'destroy']);
    Route::put('/events/{event}/move', [EventController::class, 'move']);

    // Contexts API
    Route::post('/contexts', [ContextController::class, 'store']);
    Route::delete('/contexts/{context}', [ContextController::class, 'destroy']);

    // Settings API
    Route::put('/settings/{key}', [SettingController::class, 'update']);

    // App updates
    Route::get('/api/update-status', [UpdateController::class, 'status']);
    Route::post('/api/update-apply', [UpdateController::class, 'apply']);

    // Two-factor authentication management
    Route::post('/api/2fa/setup', [TwoFactorController::class, 'setup']);
    Route::post('/api/2fa/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/api/2fa/disable', [TwoFactorController::class, 'disable']);
    Route::get('/api/2fa/status', [TwoFactorController::class, 'status']);

    // SMTP status
    Route::get('/api/smtp-status', SmtpStatusController::class);
});

// Health check (no auth)
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::select('SELECT 1');
        return response()->json(['status' => 'ok']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error'], 500);
    }
});

// Inbound email webhook (no auth, secured by secret header)
Route::post('/api/inbound-email', [InboundEmailController::class, 'store']);

require __DIR__.'/auth.php';
