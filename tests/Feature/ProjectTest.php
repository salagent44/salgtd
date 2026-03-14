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

    public function test_can_assign_project_to_item(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'My Task', 'status' => 'next-action']);

        $response = $this->post("/items/{$task->id}/assign-project", [
            'project_id' => (string) $project->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('items', ['id' => $task->id, 'project_id' => (string) $project->id]);
    }

    public function test_can_unassign_project(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'My Task', 'status' => 'next-action', 'project_id' => $project->id]);

        $response = $this->post("/items/{$task->id}/assign-project", [
            'project_id' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['id' => $task->id, 'project_id' => null]);
    }

    public function test_can_set_goal_when_processing_as_project(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Big Initiative', 'status' => 'inbox']);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'project',
            'goal' => 'Launch by end of Q2',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['id' => $item->id, 'status' => 'project', 'goal' => 'Launch by end of Q2']);
    }

    public function test_reclassifying_project_clears_goal_and_unlinks_tasks(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project', 'goal' => 'Ship it']);
        $task1 = Item::create(['id' => Str::ulid(), 'title' => 'Task 1', 'status' => 'next-action', 'project_id' => $project->id]);
        $task2 = Item::create(['id' => Str::ulid(), 'title' => 'Task 2', 'status' => 'waiting', 'project_id' => $project->id]);

        $response = $this->post("/items/{$project->id}/process", [
            'status' => 'next-action',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['id' => $project->id, 'status' => 'next-action', 'goal' => null]);
        $this->assertDatabaseHas('items', ['id' => $task1->id, 'project_id' => null]);
        $this->assertDatabaseHas('items', ['id' => $task2->id, 'project_id' => null]);
    }

    public function test_deleting_project_nullifies_linked_tasks(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'Task 1', 'status' => 'next-action', 'project_id' => $project->id]);

        $response = $this->delete("/items/{$project->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('items', ['id' => $project->id]);
        $this->assertDatabaseHas('items', ['id' => $task->id, 'project_id' => null]);
    }

    public function test_setting_item_to_project_clears_its_own_project_id(): void
    {
        $parentProject = Item::create(['id' => Str::ulid(), 'title' => 'Parent', 'status' => 'project']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'Child Task', 'status' => 'next-action', 'project_id' => $parentProject->id]);

        $response = $this->post("/items/{$task->id}/process", [
            'status' => 'project',
            'goal' => 'Now a project itself',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['id' => $task->id, 'status' => 'project', 'project_id' => null]);
    }

    public function test_assign_project_requires_auth(): void
    {
        // Log out
        $this->app['auth']->forgetGuards();

        $task = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'next-action']);

        $response = $this->post("/items/{$task->id}/assign-project", [
            'project_id' => null,
        ]);

        $response->assertRedirect('/login');
    }
}
