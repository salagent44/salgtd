<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\Context;
use App\Models\Item;
use App\Models\Note;
use Illuminate\Console\Command;

class CleanupSoftDeletes extends Command
{
    protected $signature = 'sync:cleanup';
    protected $description = 'Hard-delete records that were soft-deleted more than 30 days ago';

    public function handle(): int
    {
        $cutoff = now()->subDays(30);

        $counts = [
            'items' => Item::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'notes' => Note::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'calendar_events' => CalendarEvent::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'contexts' => Context::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
        ];

        foreach ($counts as $entity => $count) {
            if ($count > 0) {
                $this->info("Hard-deleted {$count} {$entity}");
            }
        }

        return self::SUCCESS;
    }
}
