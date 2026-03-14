<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClarifyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    // ===== Process as Next Action =====

    public function test_process_inbox_to_next_action_no_context(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Do something', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'next-action',
            'context' => null,
            'project_id' => null,
        ]);
    }

    public function test_process_inbox_to_next_action_with_context(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Call someone', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
            'context' => '@phone',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'next-action',
            'context' => '@phone',
        ]);
    }

    public function test_process_next_action_with_context_and_project(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'Big Project', 'status' => 'project']);
        $item = Item::create(['id' => Str::ulid(), 'title' => 'First step', 'status' => 'inbox']);

        // Assign project first (separate call, like the UI does)
        $this->post("/items/{$item->id}/assign-project", [
            'project_id' => (string) $project->id,
        ]);

        // Then process as next-action with context
        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
            'context' => '@computer',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('next-action', $item->status);
        $this->assertEquals('@computer', $item->context);
        $this->assertEquals((string) $project->id, $item->project_id);
    }

    public function test_assign_project_preserves_across_process(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project']);
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'inbox']);

        // Assign project
        $this->post("/items/{$item->id}/assign-project", [
            'project_id' => (string) $project->id,
        ]);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'project_id' => (string) $project->id]);

        // Process to next-action — project_id should survive
        $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
            'context' => '@home',
        ]);

        $item->refresh();
        $this->assertEquals('next-action', $item->status);
        $this->assertEquals('@home', $item->context);
        $this->assertEquals((string) $project->id, $item->project_id);
    }

    public function test_assign_project_then_unassign(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'Project', 'status' => 'project']);
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'next-action']);

        $this->post("/items/{$item->id}/assign-project", ['project_id' => (string) $project->id]);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'project_id' => (string) $project->id]);

        $this->post("/items/{$item->id}/assign-project", ['project_id' => null]);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'project_id' => null]);
    }

    // ===== Process as Project =====

    public function test_process_inbox_to_project_with_goal(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Big initiative', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'project',
            'goal' => 'Ship by end of quarter',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'project',
            'goal' => 'Ship by end of quarter',
            'project_id' => null,
        ]);
    }

    public function test_process_as_project_without_goal(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Quick project', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'project',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'project',
            'goal' => null,
        ]);
    }

    public function test_process_as_project_clears_own_project_id(): void
    {
        $parent = Item::create(['id' => Str::ulid(), 'title' => 'Parent', 'status' => 'project']);
        $child = Item::create(['id' => Str::ulid(), 'title' => 'Child', 'status' => 'next-action', 'project_id' => $parent->id]);

        $this->post("/items/{$child->id}/process", [
            'status' => 'project',
            'goal' => 'Promoted',
        ]);

        $child->refresh();
        $this->assertEquals('project', $child->status);
        $this->assertNull($child->project_id);
    }

    // ===== Reclassify Project =====

    public function test_reclassify_project_to_next_action_clears_goal(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'Was a project', 'status' => 'project', 'goal' => 'Old goal']);

        $this->post("/items/{$project->id}/process", ['status' => 'next-action']);

        $project->refresh();
        $this->assertEquals('next-action', $project->status);
        $this->assertNull($project->goal);
    }

    public function test_reclassify_project_unlinks_all_tasks(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project', 'goal' => 'Ship it']);
        $task1 = Item::create(['id' => Str::ulid(), 'title' => 'Task 1', 'status' => 'next-action', 'project_id' => $project->id]);
        $task2 = Item::create(['id' => Str::ulid(), 'title' => 'Task 2', 'status' => 'waiting', 'project_id' => $project->id]);

        $this->post("/items/{$project->id}/process", ['status' => 'next-action']);

        $this->assertDatabaseHas('items', ['id' => $task1->id, 'project_id' => null]);
        $this->assertDatabaseHas('items', ['id' => $task2->id, 'project_id' => null]);
    }

    // ===== Process as Waiting =====

    public function test_process_to_waiting_with_details(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Waiting task', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'waiting',
            'waiting_for' => 'Design team',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'waiting',
            'waiting_for' => 'Design team',
        ]);
    }

    // ===== Process as Tickler =====

    public function test_process_to_tickler(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Future task', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'tickler',
            'tickler_date' => '2026-06-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'tickler',
            'tickler_date' => '2026-06-01',
        ]);
    }

    // ===== Process as Done =====

    public function test_process_to_done(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Quick task', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", ['status' => 'done']);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('done', $item->status);
        $this->assertNotNull($item->completed_at);
    }

    // ===== Process with title edit =====

    public function test_process_can_rename_title(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Old name', 'status' => 'inbox']);

        $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
            'title' => 'Better name',
            'context' => '@work',
        ]);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'title' => 'Better name',
            'status' => 'next-action',
            'context' => '@work',
        ]);
    }

    // ===== Move to inbox =====

    public function test_move_to_inbox_clears_context_and_project(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'Proj', 'status' => 'project']);
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Task',
            'status' => 'next-action',
            'context' => '@work',
            'project_id' => $project->id,
        ]);

        $this->post("/items/{$item->id}/move-to-inbox");

        $item->refresh();
        $this->assertEquals('inbox', $item->status);
        $this->assertNull($item->context);
        // project_id is NOT cleared on move-to-inbox (per current behavior)
    }

    // ===== Delete project cascades =====

    public function test_delete_project_nullifies_tasks_project_id(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'Doomed Project', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'Orphan Task', 'status' => 'next-action', 'project_id' => $project->id]);

        $this->delete("/items/{$project->id}");

        $this->assertSoftDeleted('items', ['id' => $project->id]);
        $task->refresh();
        $this->assertNull($task->project_id);
    }

    // ===== Create item with project_id =====

    public function test_create_next_action_with_project(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'Project', 'status' => 'project']);

        $response = $this->post('/items', [
            'title' => 'New task for project',
            'status' => 'next-action',
            'project_id' => (string) $project->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'title' => 'New task for project',
            'status' => 'next-action',
            'project_id' => (string) $project->id,
        ]);
    }

    // ===== Auth required =====

    public function test_process_requires_auth(): void
    {
        $this->app['auth']->forgetGuards();

        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", ['status' => 'next-action']);
        $response->assertRedirect('/login');
    }

    public function test_assign_project_requires_auth(): void
    {
        $this->app['auth']->forgetGuards();

        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'next-action']);

        $response = $this->post("/items/{$item->id}/assign-project", ['project_id' => null]);
        $response->assertRedirect('/login');
    }

    // ===== Bulk operations =====

    public function test_bulk_process_to_next_action(): void
    {
        $items = collect(range(1, 3))->map(fn ($i) =>
            Item::create(['id' => Str::ulid(), 'title' => "Item $i", 'status' => 'inbox'])
        );

        $response = $this->post('/items/bulk-process', [
            'ids' => $items->pluck('id')->toArray(),
            'status' => 'next-action',
        ]);

        $response->assertRedirect();
        foreach ($items as $item) {
            $this->assertDatabaseHas('items', ['id' => $item->id, 'status' => 'next-action']);
        }
    }

    public function test_bulk_process_to_done(): void
    {
        $items = collect(range(1, 2))->map(fn ($i) =>
            Item::create(['id' => Str::ulid(), 'title' => "Item $i", 'status' => 'inbox'])
        );

        $response = $this->post('/items/bulk-process', [
            'ids' => $items->pluck('id')->toArray(),
            'status' => 'done',
        ]);

        $response->assertRedirect();
        foreach ($items as $item) {
            $item->refresh();
            $this->assertEquals('done', $item->status);
            $this->assertNotNull($item->completed_at);
        }
    }

    // ===== Edge cases =====

    public function test_cannot_assign_nonexistent_project(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'next-action']);

        $response = $this->post("/items/{$item->id}/assign-project", [
            'project_id' => 'nonexistent-id',
        ]);

        $response->assertSessionHasErrors('project_id');
    }

    public function test_process_validates_status(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'invalid-status',
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_reassign_project_replaces_old_one(): void
    {
        $project1 = Item::create(['id' => Str::ulid(), 'title' => 'Project A', 'status' => 'project']);
        $project2 = Item::create(['id' => Str::ulid(), 'title' => 'Project B', 'status' => 'project']);
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'next-action', 'project_id' => $project1->id]);

        $this->post("/items/{$item->id}/assign-project", ['project_id' => (string) $project2->id]);

        $item->refresh();
        $this->assertEquals((string) $project2->id, $item->project_id);
    }

    public function test_moving_next_action_to_waiting_clears_context(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'next-action', 'context' => '@phone']);

        $this->post("/items/{$item->id}/process", ['status' => 'waiting', 'waiting_for' => 'Sarah']);

        $item->refresh();
        $this->assertEquals('waiting', $item->status);
        $this->assertEquals('Sarah', $item->waiting_for);
        $this->assertNull($item->context);
    }

    public function test_moving_waiting_to_next_action_clears_waiting_fields(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'waiting', 'waiting_for' => 'John', 'waiting_date' => '2026-04-01']);

        $this->post("/items/{$item->id}/process", ['status' => 'next-action', 'context' => '@home']);

        $item->refresh();
        $this->assertEquals('next-action', $item->status);
        $this->assertEquals('@home', $item->context);
        $this->assertNull($item->waiting_for);
        $this->assertNull($item->waiting_date);
    }

    public function test_moving_tickler_to_next_action_clears_tickler_date(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'tickler', 'tickler_date' => '2026-06-01']);

        $this->post("/items/{$item->id}/process", ['status' => 'next-action', 'context' => '@work']);

        $item->refresh();
        $this->assertEquals('next-action', $item->status);
        $this->assertNull($item->tickler_date);
    }

    public function test_process_multiple_times_updates_correctly(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Flip-flopper', 'status' => 'inbox']);

        // First: next-action
        $this->post("/items/{$item->id}/process", ['status' => 'next-action', 'context' => '@home']);
        $item->refresh();
        $this->assertEquals('next-action', $item->status);
        $this->assertEquals('@home', $item->context);

        // Second: waiting (context should clear)
        $this->post("/items/{$item->id}/process", ['status' => 'waiting', 'waiting_for' => 'Boss']);
        $item->refresh();
        $this->assertEquals('waiting', $item->status);
        $this->assertEquals('Boss', $item->waiting_for);
        $this->assertNull($item->context);

        // Third: project
        $this->post("/items/{$item->id}/process", ['status' => 'project', 'goal' => 'Become big']);
        $item->refresh();
        $this->assertEquals('project', $item->status);
        $this->assertEquals('Become big', $item->goal);

        // Fourth: back to next-action (goal should clear)
        $this->post("/items/{$item->id}/process", ['status' => 'next-action']);
        $item->refresh();
        $this->assertEquals('next-action', $item->status);
        $this->assertNull($item->goal);
    }

    public function test_remove_context_from_next_action(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Task with context',
            'status' => 'next-action',
            'context' => '@phone',
        ]);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
            'context' => null,
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('next-action', $item->status);
        $this->assertNull($item->context);
    }

    public function test_change_context_on_next_action(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Task with context',
            'status' => 'next-action',
            'context' => '@phone',
        ]);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
            'context' => '@home',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('@home', $item->context);
    }
}
