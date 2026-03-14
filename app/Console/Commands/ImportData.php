<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\Item;
use App\Models\Note;
use App\Models\NoteTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportData extends Command
{
    protected $signature = 'gtd:import {--file= : Path to the JSON export file}';

    protected $description = 'Import data from a localStorage JSON export into the GTD database';

    public function handle(): int
    {
        $file = $this->option('file');

        if (! $file) {
            $this->error('Please provide a file path with --file=export.json');
            return self::FAILURE;
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: ' . json_last_error_msg());
            return self::FAILURE;
        }

        DB::transaction(function () use ($data) {
            $this->importItems($data['items'] ?? []);
            $this->importNotes($data['notes'] ?? []);
            $this->importEvents($data['events'] ?? []);
        });

        $this->info('Import complete.');

        return self::SUCCESS;
    }

    private function importItems(array $items): void
    {
        $this->info("Importing " . count($items) . " items...");

        foreach ($items as $item) {
            Item::updateOrCreate(
                ['id' => $item['id']],
                [
                    'title' => $item['title'] ?? null,
                    'status' => $item['status'] ?? 'inbox',
                    'context' => $item['context'] ?? null,
                    'waiting_for' => $item['waitingFor'] ?? null,
                    'waiting_date' => $item['waitingDate'] ?? null,
                    'tickler_date' => $item['ticklerDate'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }

        $this->info("Items imported successfully.");
    }

    private function importNotes(array $notes): void
    {
        $this->info("Importing " . count($notes) . " notes...");

        foreach ($notes as $note) {
            $noteRecord = Note::updateOrCreate(
                ['id' => $note['id']],
                [
                    'title' => $note['title'] ?? null,
                    'content' => $note['content'] ?? null,
                    'pinned' => $note['pinned'] ?? false,
                    'trashed' => $note['trashed'] ?? false,
                    'locked' => $note['locked'] ?? false,
                ]
            );

            if (! empty($note['createdAt'])) {
                $noteRecord->created_at = $note['createdAt'];
            }
            if (! empty($note['updatedAt'])) {
                $noteRecord->updated_at = $note['updatedAt'];
            }
            $noteRecord->save();

            // Sync tags: remove old ones, insert current set
            if (isset($note['tags'])) {
                NoteTag::where('note_id', $noteRecord->id)->delete();

                foreach ($note['tags'] as $tag) {
                    NoteTag::create([
                        'note_id' => $noteRecord->id,
                        'tag' => $tag,
                    ]);
                }
            }
        }

        $this->info("Notes imported successfully.");
    }

    private function importEvents(array $events): void
    {
        $this->info("Importing " . count($events) . " calendar events...");

        foreach ($events as $event) {
            CalendarEvent::updateOrCreate(
                ['id' => $event['id']],
                [
                    'title' => $event['title'] ?? null,
                    'event_date' => $event['date'] ?? null,
                    'event_time' => $event['time'] ?? null,
                    'description' => $event['description'] ?? null,
                    'color' => $event['color'] ?? null,
                ]
            );
        }

        $this->info("Calendar events imported successfully.");
    }
}
