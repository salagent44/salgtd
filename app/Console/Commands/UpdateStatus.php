<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class UpdateStatus extends Command
{
    protected $signature = 'update:status {status : One of: updating, done, error, idle}';
    protected $description = 'Set the app update status (used by the auto-update script)';

    public function handle(): int
    {
        $status = $this->argument('status');
        $allowed = ['updating', 'done', 'error', 'idle'];

        if (!in_array($status, $allowed)) {
            $this->error("Invalid status. Must be one of: " . implode(', ', $allowed));
            return 1;
        }

        Setting::set('update_status', $status);

        $this->info("Update status set to: {$status}");
        return 0;
    }
}
