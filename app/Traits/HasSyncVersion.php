<?php

namespace App\Traits;

use App\Events\SyncUpdated;
use Illuminate\Support\Facades\DB;

trait HasSyncVersion
{
    public static function bootHasSyncVersion(): void
    {
        static::saving(function ($model) {
            $model->sync_version = static::nextSyncVersion();
        });

        static::saved(function ($model) {
            SyncUpdated::safeBroadcast($model);
        });

        static::deleting(function ($model) {
            // For soft deletes, bump version so the deletion appears in sync pulls
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                $model->sync_version = static::nextSyncVersion();
                $model->saveQuietly();
            }
        });

        static::deleted(function ($model) {
            SyncUpdated::safeBroadcast($model);
        });
    }

    protected static function nextSyncVersion(): int
    {
        DB::table('sync_cursor')->where('id', 1)->increment('version');

        return (int) DB::table('sync_cursor')->where('id', 1)->value('version');
    }
}
