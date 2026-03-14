<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--path= : Custom backup directory} {--keep=7 : Number of backups to keep}';
    protected $description = 'Back up the SQLite database with timestamped filename';

    public function handle(): int
    {
        $dbPath = config('database.connections.sqlite.database');
        if (!$dbPath || !file_exists($dbPath)) {
            $this->error("Database file not found: {$dbPath}");
            return 1;
        }

        $backupDir = $this->option('path') ?: dirname($dbPath) . '/backups';
        $keep = (int) $this->option('keep');

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $backupFile = "{$backupDir}/gtd_{$timestamp}.sqlite";

        // Use SQLite's backup API via the .backup command for a safe copy
        $result = null;
        $output = [];
        exec(
            sprintf('sqlite3 %s ".backup %s" 2>&1', escapeshellarg($dbPath), escapeshellarg($backupFile)),
            $output,
            $result
        );

        if ($result !== 0 || !file_exists($backupFile)) {
            // Fallback to file copy
            if (!copy($dbPath, $backupFile)) {
                $this->error('Backup failed');
                return 1;
            }
        }

        $size = round(filesize($backupFile) / 1024, 1);
        $this->info("Backup created: {$backupFile} ({$size} KB)");

        // Prune old backups
        if ($keep > 0) {
            $backups = glob("{$backupDir}/gtd_*.sqlite");
            sort($backups);
            $toDelete = array_slice($backups, 0, max(0, count($backups) - $keep));
            foreach ($toDelete as $old) {
                unlink($old);
                $this->line("Pruned old backup: " . basename($old));
            }
        }

        return 0;
    }
}
