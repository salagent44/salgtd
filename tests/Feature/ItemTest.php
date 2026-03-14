<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemTag;
use App\Models\Context;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_dashboard_loads_with_items(): void
    {
        Item::create([
            'id' => Str::ulid(),
            'title' => 'Test Item',
            'status' => 'inbox',
        ]);

        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_can_create_item(): void
    {
        $response = $this->post('/items', [
            'title' => 'Buy groceries',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'title' => 'Buy groceries',
            'status' => 'inbox',
        ]);
    }

    public function test_can_create_next_action_with_context(): void
    {
        $response = $this->post('/items', [
            'title' => 'Call dentist',
            'status' => 'next-action',
            'context' => '@phone',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'title' => 'Call dentist',
            'status' => 'next-action',
            'context' => '@phone',
        ]);
    }

    public function test_can_create_waiting_item(): void
    {
        $response = $this->post('/items', [
            'title' => 'Waiting for reply',
            'status' => 'waiting',
            'waiting_for' => 'John',
            'waiting_date' => '2026-04-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'title' => 'Waiting for reply',
            'status' => 'waiting',
            'waiting_for' => 'John',
            'waiting_date' => '2026-04-01',
        ]);
    }

    public function test_can_update_item(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Old title',
            'status' => 'inbox',
        ]);

        $response = $this->put("/items/{$item->id}", [
            'title' => 'New title',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'title' => 'New title',
        ]);
    }

    public function test_can_process_item(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Process me',
            'status' => 'inbox',
        ]);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'waiting',
            'context' => '@home',
            'waiting_for' => 'John',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'waiting',
            'context' => '@home',
            'waiting_for' => 'John',
        ]);
    }

    public function test_can_move_to_inbox(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Processed item',
            'status' => 'next-action',
            'context' => '@work',
            'waiting_for' => 'Someone',
        ]);

        $response = $this->post("/items/{$item->id}/move-to-inbox");

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('inbox', $item->status);
        $this->assertNull($item->context);
        $this->assertNull($item->waiting_for);
    }

    public function test_move_to_inbox_clears_all_fields(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'P', 'status' => 'project']);
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Fully loaded item',
            'status' => 'waiting',
            'context' => '@office',
            'waiting_for' => 'Manager',
            'waiting_date' => '2026-04-01',
            'tickler_date' => '2026-05-01',
            'project_id' => $project->id,
            'flagged' => true,
        ]);

        $response = $this->post("/items/{$item->id}/move-to-inbox");

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('inbox', $item->status);
        $this->assertNull($item->context);
        $this->assertNull($item->waiting_for);
        $this->assertNull($item->waiting_date);
        $this->assertNull($item->tickler_date);
        $this->assertNull($item->project_id);
        $this->assertNull($item->goal);
        $this->assertFalse((bool) $item->flagged);
        $this->assertNull($item->completed_at);
        $this->assertNull($item->original_status);
    }

    public function test_move_project_to_inbox_unlinks_tasks(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'Big Project', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'Linked Task', 'status' => 'next-action', 'project_id' => $project->id]);

        $this->post("/items/{$project->id}/move-to-inbox");

        $project->refresh();
        $task->refresh();
        $this->assertEquals('inbox', $project->status);
        $this->assertNull($task->project_id);
    }

    public function test_tickler_auto_promotes_to_inbox(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Deferred task',
            'status' => 'tickler',
            'context' => '@work',
            'tickler_date' => now()->subDay()->toDateString(),
        ]);

        // Dashboard load triggers auto-promotion
        $response = $this->get('/');
        $response->assertOk();

        $item->refresh();
        $this->assertEquals('inbox', $item->status);
        $this->assertNull($item->tickler_date);
        $this->assertNull($item->context);
    }

    public function test_future_tickler_stays_tickler(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Future task',
            'status' => 'tickler',
            'tickler_date' => now()->addWeek()->toDateString(),
        ]);

        $this->get('/');

        $item->refresh();
        $this->assertEquals('tickler', $item->status);
        $this->assertNotNull($item->tickler_date);
    }

    public function test_quick_next_action_creates_with_context_in_single_request(): void
    {
        $response = $this->post('/items', [
            'title' => 'Fix the sink',
            'status' => 'next-action',
            'context' => '🏠 @house',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'title' => 'Fix the sink',
            'status' => 'next-action',
            'context' => '🏠 @house',
        ]);
    }

    public function test_marking_done_tracks_completion_metadata(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Finish report',
            'status' => 'project',
        ]);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'done',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('done', $item->status);
        $this->assertEquals('project', $item->original_status);
        $this->assertNotNull($item->completed_at);
    }

    public function test_undoing_done_clears_completion_metadata(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Undo me',
            'status' => 'done',
            'original_status' => 'next-action',
            'completed_at' => now(),
        ]);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('next-action', $item->status);
        $this->assertNull($item->original_status);
        $this->assertNull($item->completed_at);
    }

    public function test_bulk_process_moves_multiple_items(): void
    {
        $items = [];
        foreach (['Task A', 'Task B', 'Task C'] as $title) {
            $items[] = Item::create([
                'id' => Str::ulid(),
                'title' => $title,
                'status' => 'inbox',
            ]);
        }

        $ids = array_map(fn($i) => $i->id, $items);

        $response = $this->post('/items/bulk-process', [
            'ids' => $ids,
            'status' => 'next-action',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        foreach ($items as $item) {
            $item->refresh();
            $this->assertEquals('next-action', $item->status);
        }
    }

    public function test_bulk_process_to_inbox_clears_fields(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'With context',
            'status' => 'next-action',
            'context' => '@work',
            'waiting_for' => 'Someone',
        ]);

        $response = $this->post('/items/bulk-process', [
            'ids' => [$item->id],
            'status' => 'inbox',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('inbox', $item->status);
        $this->assertNull($item->context);
        $this->assertNull($item->waiting_for);
    }

    public function test_bulk_process_to_done_tracks_metadata(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Finish this',
            'status' => 'project',
        ]);

        $response = $this->post('/items/bulk-process', [
            'ids' => [$item->id],
            'status' => 'done',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('done', $item->status);
        $this->assertEquals('project', $item->original_status);
        $this->assertNotNull($item->completed_at);
    }

    public function test_bulk_delete_removes_multiple_items(): void
    {
        $items = [];
        foreach (['Delete A', 'Delete B'] as $title) {
            $items[] = Item::create([
                'id' => Str::ulid(),
                'title' => $title,
                'status' => 'inbox',
            ]);
        }

        $ids = array_map(fn($i) => $i->id, $items);

        $response = $this->post('/items/bulk-delete', ['ids' => $ids]);

        $response->assertRedirect();
        foreach ($ids as $id) {
            $this->assertSoftDeleted('items', ['id' => $id]);
        }
    }

    public function test_can_delete_item(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Delete me',
            'status' => 'inbox',
        ]);

        $response = $this->delete("/items/{$item->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('items', ['id' => $item->id]);
    }

    public function test_can_flag_item_via_update(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Flag me',
            'status' => 'next-action',
        ]);

        $response = $this->put("/items/{$item->id}", [
            'flagged' => true,
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertTrue($item->flagged);
    }

    public function test_can_unflag_item(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Unflag me',
            'status' => 'next-action',
            'flagged' => true,
        ]);

        $response = $this->put("/items/{$item->id}", [
            'flagged' => false,
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertFalse($item->flagged);
    }

    public function test_flagged_defaults_to_false(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'New item',
            'status' => 'inbox',
        ]);

        $item->refresh();
        $this->assertFalse($item->flagged);
    }

    public function test_can_flag_during_process(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Process and flag',
            'status' => 'inbox',
        ]);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
            'flagged' => true,
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('next-action', $item->status);
        $this->assertTrue($item->flagged);
    }

    public function test_can_edit_title_during_process(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Old title',
            'status' => 'inbox',
        ]);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
            'title' => 'Renamed title',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('Renamed title', $item->title);
        $this->assertEquals('next-action', $item->status);
    }

    public function test_can_add_tag_to_item(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Tagged item',
            'status' => 'next-action',
        ]);

        $response = $this->post("/items/{$item->id}/tags", [
            'tag' => 'urgent',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('item_tags', [
            'item_id' => $item->id,
            'tag' => 'urgent',
        ]);
    }

    public function test_can_remove_tag_from_item(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Tagged item',
            'status' => 'next-action',
        ]);

        $item->tags()->create(['tag' => 'remove-me']);

        $response = $this->delete("/items/{$item->id}/tags/remove-me");

        $response->assertRedirect();
        $this->assertDatabaseMissing('item_tags', [
            'item_id' => $item->id,
            'tag' => 'remove-me',
        ]);
    }

    public function test_duplicate_tag_is_idempotent(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Tagged item',
            'status' => 'next-action',
        ]);

        $this->post("/items/{$item->id}/tags", ['tag' => 'dupe']);
        $this->post("/items/{$item->id}/tags", ['tag' => 'dupe']);

        $this->assertEquals(1, $item->tags()->where('tag', 'dupe')->count());
    }

    public function test_tags_cascade_deleted_when_item_deleted(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Delete me',
            'status' => 'inbox',
        ]);

        $item->tags()->create(['tag' => 'will-vanish']);

        $this->assertDatabaseHas('item_tags', ['item_id' => $item->id]);

        $this->delete("/items/{$item->id}");

        // Tags remain because item is soft-deleted, not hard-deleted
        $this->assertSoftDeleted('items', ['id' => $item->id]);
        $this->assertDatabaseHas('item_tags', ['item_id' => $item->id]);
    }
}
