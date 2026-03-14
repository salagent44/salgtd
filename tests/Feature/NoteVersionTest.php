<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NoteVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_create_version(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Versioned Note',
            'content' => 'Version 1 content',
        ]);

        $response = $this->post("/notes/{$note->id}/versions");

        $response->assertNoContent();
        $this->assertDatabaseHas('note_versions', [
            'note_id' => $note->id,
            'title' => 'Versioned Note',
            'content' => 'Version 1 content',
        ]);
    }

    public function test_skips_duplicate_version(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Versioned Note',
            'content' => 'Same content',
        ]);

        // Create first version
        $this->post("/notes/{$note->id}/versions");
        $this->assertCount(1, $note->versions);

        // Try to create duplicate - should be skipped
        $response = $this->post("/notes/{$note->id}/versions");
        $response->assertNoContent();
        $this->assertCount(1, $note->fresh()->versions);
    }

    public function test_can_list_versions(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Versioned Note',
            'content' => 'Content v1',
        ]);

        $note->versions()->create([
            'title' => 'Versioned Note',
            'content' => 'Content v1',
        ]);

        $response = $this->get("/notes/{$note->id}/versions");

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_can_restore_version(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Current Title',
            'content' => 'Current content',
        ]);

        $version = $note->versions()->create([
            'title' => 'Old Title',
            'content' => 'Old content',
        ]);

        $response = $this->post("/notes/{$note->id}/versions/{$version->id}/restore");

        $response->assertRedirect();
        $note->refresh();
        $this->assertEquals('Old Title', $note->title);
        $this->assertEquals('Old content', $note->content);

        // Should have created a backup version of the current state
        $this->assertCount(2, $note->versions);
    }
}
