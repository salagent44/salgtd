<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $entity,
        public string $id,
        public int $syncVersion,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sync')];
    }

    public function broadcastAs(): string
    {
        return 'SyncUpdated';
    }

    public static function safeBroadcast($model): void
    {
        // Don't broadcast if reverb isn't configured
        if (config('broadcasting.default') === 'log' || config('broadcasting.default') === 'null') {
            return;
        }

        $entity = match (true) {
            $model instanceof \App\Models\Item => 'item',
            $model instanceof \App\Models\Note => 'note',
            $model instanceof \App\Models\CalendarEvent => 'calendar_event',
            $model instanceof \App\Models\Context => 'context',
            $model instanceof \App\Models\Setting => 'setting',
            default => 'unknown',
        };

        broadcast(new static(
            entity: $entity,
            id: (string) $model->getKey(),
            syncVersion: (int) $model->sync_version,
        ))->toOthers();
    }
}
