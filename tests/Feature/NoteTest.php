<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_create_note(): void
    {
        $response = $this->post('/notes', [
            'title' => 'My Note',
            'content' => 'Some content',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'title' => 'My Note',
            'content' => 'Some content',
        ]);
    }

    public function test_can_update_note_content(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Original',
            'content' => 'Old content',
        ]);

        $response = $this->put("/notes/{$note->id}", [
            'content' => 'Updated content',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'content' => 'Updated content',
        ]);
    }

    public function test_can_add_tag(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Tagged note',
            'content' => '',
        ]);

        $response = $this->post("/notes/{$note->id}/tags", [
            'tag' => 'important',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('note_tags', [
            'note_id' => $note->id,
            'tag' => 'important',
        ]);
    }

    public function test_can_remove_tag(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Tagged note',
            'content' => '',
        ]);

        $note->tags()->create(['tag' => 'removeme']);

        $response = $this->delete("/notes/{$note->id}/tags/removeme");

        $response->assertRedirect();
        $this->assertDatabaseMissing('note_tags', [
            'note_id' => $note->id,
            'tag' => 'removeme',
        ]);
    }

    public function test_can_pin_note(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Pin me',
            'content' => '',
            'pinned' => false,
        ]);

        $response = $this->put("/notes/{$note->id}/toggle-pin");

        $response->assertRedirect();
        $note->refresh();
        $this->assertTrue($note->pinned);

        // Toggle again
        $this->put("/notes/{$note->id}/toggle-pin");
        $note->refresh();
        $this->assertFalse($note->pinned);
    }

    public function test_can_trash_and_restore_note(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Trash me',
            'content' => '',
        ]);

        $response = $this->put("/notes/{$note->id}/trash");
        $response->assertRedirect();
        $note->refresh();
        $this->assertTrue($note->trashed);

        $response = $this->put("/notes/{$note->id}/restore");
        $response->assertRedirect();
        $note->refresh();
        $this->assertFalse($note->trashed);
    }

    public function test_can_lock_note(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Lock me',
            'content' => '',
            'locked' => false,
        ]);

        $response = $this->put("/notes/{$note->id}/toggle-lock");

        $response->assertRedirect();
        $note->refresh();
        $this->assertTrue($note->locked);
    }

    public function test_can_permanently_delete_note(): void
    {
        $note = Note::create([
            'id' => Str::ulid(),
            'title' => 'Delete me forever',
            'content' => '',
        ]);

        $response = $this->delete("/notes/{$note->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }
}
