<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_process_as_project_creates_project_and_next_action(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Launch website', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process-as-project", [
            'next_action_title' => 'Buy domain name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['id' => $item->id, 'status' => 'project']);
        $this->assertDatabaseHas('items', [
            'title' => 'Buy domain name',
            'status' => 'next-action',
            'project_id' => $item->id,
        ]);
    }

    public function test_process_as_project_with_renamed_title(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'old title', 'status' => 'inbox']);

        $this->post("/items/{$item->id}/process-as-project", [
            'title' => 'New Project Name',
            'next_action_title' => 'First step',
        ]);

        $this->assertDatabaseHas('items', ['id' => $item->id, 'title' => 'New Project Name', 'status' => 'project']);
    }

    public function test_process_as_project_clears_action_fields(): void
    {
        $parent = Item::create(['id' => Str::ulid(), 'title' => 'Parent', 'status' => 'project']);
        $item = Item::create([
            'id' => Str::ulid(), 'title' => 'Test', 'status' => 'next-action',
            'context' => '@home', 'waiting_for' => 'Bob', 'project_id' => $parent->id,
        ]);

        $this->post("/items/{$item->id}/process-as-project", [
            'next_action_title' => 'Do something',
        ]);

        $item->refresh();
        $this->assertEquals('project', $item->status);
        $this->assertNull($item->project_id);
        $this->assertNull($item->context);
        $this->assertNull($item->waiting_for);
    }

    public function test_set_next_action_for_project(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project']);

        $response = $this->post("/items/{$project->id}/set-next-action", [
            'next_action_title' => 'Call vendor',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'title' => 'Call vendor',
            'status' => 'next-action',
            'project_id' => $project->id,
        ]);
    }

    public function test_set_next_action_unlinks_existing(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project']);
        $oldAction = Item::create(['id' => Str::ulid(), 'title' => 'Old action', 'status' => 'next-action', 'project_id' => $project->id]);

        $this->post("/items/{$project->id}/set-next-action", [
            'next_action_title' => 'New action',
        ]);

        $oldAction->refresh();
        $this->assertNull($oldAction->project_id);
        $this->assertDatabaseHas('items', [
            'title' => 'New action',
            'status' => 'next-action',
            'project_id' => $project->id,
        ]);
    }

    public function test_reclassifying_project_unlinks_tasks(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'Task 1', 'status' => 'next-action', 'project_id' => $project->id]);

        $this->post("/items/{$project->id}/process", ['status' => 'next-action']);

        $this->assertDatabaseHas('items', ['id' => $project->id, 'status' => 'next-action']);
        $this->assertDatabaseHas('items', ['id' => $task->id, 'project_id' => null]);
    }

    public function test_deleting_project_nullifies_linked_tasks(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'Task 1', 'status' => 'next-action', 'project_id' => $project->id]);

        $this->delete("/items/{$project->id}");

        $this->assertSoftDeleted('items', ['id' => $project->id]);
        $this->assertDatabaseHas('items', ['id' => $task->id, 'project_id' => null]);
    }

    public function test_setting_item_to_project_clears_its_own_project_id(): void
    {
        $parentProject = Item::create(['id' => Str::ulid(), 'title' => 'Parent', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'Child', 'status' => 'next-action', 'project_id' => $parentProject->id]);

        $this->post("/items/{$task->id}/process-as-project", [
            'next_action_title' => 'First step',
        ]);

        $this->assertDatabaseHas('items', ['id' => $task->id, 'status' => 'project', 'project_id' => null]);
    }

    public function test_process_as_project_requires_next_action(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Test', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process-as-project", []);

        $response->assertSessionHasErrors('next_action_title');
    }

    public function test_can_assign_project_to_item(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'My Task', 'status' => 'next-action']);

        $response = $this->post("/items/{$task->id}/assign-project", [
            'project_id' => (string) $project->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['id' => $task->id, 'project_id' => (string) $project->id]);
    }
}
