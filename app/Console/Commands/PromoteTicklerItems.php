<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;

class PromoteTicklerItems extends Command
{
    protected $signature = 'items:promote-tickler';
    protected $description = 'Promote tickler items whose date has arrived to inbox';

    public function handle(): int
    {
        $items = Item::where('status', 'tickler')
            ->whereNotNull('tickler_date')
            ->where('tickler_date', '<=', now()->toDateString())
            ->get();

        foreach ($items as $item) {
            $item->update([
                'status' => 'inbox',
                'tickler_date' => null,
                'context' => null,
            ]);
        }

        if ($items->count() > 0) {
            $this->info("Promoted {$items->count()} tickler items to inbox");
        }

        return self::SUCCESS;
    }
}
