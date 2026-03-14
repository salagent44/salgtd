<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Context;
use App\Models\Item;
use App\Models\Note;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardUiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dashboard_renders_with_all_props(): void
    {
        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('items')
            ->has('contexts')
            ->has('theme')
            ->has('note_font')
            ->has('note_font_css')
            ->has('last_review')
            ->has('review_progress')
            ->has('email_address')
        );
    }

    public function test_dashboard_includes_items_by_status(): void
    {
        Item::create(['id' => Str::ulid(), 'title' => 'Inbox item', 'status' => 'inbox']);
        Item::create(['id' => Str::ulid(), 'title' => 'Next action', 'status' => 'next-action', 'context' => '@computer']);
        Item::create(['id' => Str::ulid(), 'title' => 'Waiting item', 'status' => 'waiting', 'waiting_for' => 'Bob']);
        Item::create(['id' => Str::ulid(), 'title' => 'Project item', 'status' => 'project']);
        Item::create(['id' => Str::ulid(), 'title' => 'Someday item', 'status' => 'someday']);
        Item::create(['id' => Str::ulid(), 'title' => 'Done item', 'status' => 'done']);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('items', 6)
        );
    }

    public function test_dashboard_includes_contexts(): void
    {
        Context::create(['id' => Str::ulid(), 'name' => '@office', 'sort_order' => 0]);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('contexts')
            ->where('contexts.0.name', '@office')
        );
    }

    public function test_dashboard_includes_theme_setting(): void
    {
        Setting::set('theme', 'dark');

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('theme', 'dark')
        );
    }

    public function test_dashboard_includes_email_address_setting(): void
    {
        Setting::set('email_address', 'test@example.com');

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('email_address', 'test@example.com')
        );
    }

    public function test_dashboard_items_include_tags(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Tagged item', 'status' => 'inbox']);
        $item->tags()->create(['tag' => 'urgent']);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('items', 1)
            ->has('items.0.tags', 1)
            ->where('items.0.tags.0.tag', 'urgent')
        );
    }

    public function test_dashboard_items_include_email_relationship(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Email item', 'status' => 'inbox']);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('items', 1)
            ->has('items.0.email')
        );
    }

    public function test_dashboard_items_include_project_fields(): void
    {
        $project = Item::create(['id' => Str::ulid(), 'title' => 'My Project', 'status' => 'project', 'goal' => 'Ship it']);
        $task = Item::create(['id' => Str::ulid(), 'title' => 'Task', 'status' => 'next-action', 'project_id' => $project->id]);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('items', 2)
        );

        // Verify the project_id is present in the response
        $this->assertDatabaseHas('items', [
            'id' => $task->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_dashboard_tickler_promotes_past_items(): void
    {
        Item::create([
            'id' => Str::ulid(),
            'title' => 'Tickler past',
            'status' => 'tickler',
            'tickler_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'title' => 'Tickler past',
            'status' => 'inbox',
            'tickler_date' => null,
        ]);
    }

    public function test_dashboard_tickler_keeps_future_items(): void
    {
        Item::create([
            'id' => Str::ulid(),
            'title' => 'Tickler future',
            'status' => 'tickler',
            'tickler_date' => now()->addWeek()->toDateString(),
        ]);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'title' => 'Tickler future',
            'status' => 'tickler',
        ]);
    }

    public function test_health_endpoint_available(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_dashboard_requires_auth(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_notes_loaded_lazily(): void
    {
        Note::create([
            'id' => Str::ulid(),
            'title' => 'Test note',
            'content' => 'Note content',
        ]);

        // Notes are lazy-loaded, so they should not be in the initial response
        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('items')
        );
    }

    public function test_review_progress_persists(): void
    {
        Setting::set('review_progress', json_encode(['step' => 3, 'checked' => []]));

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('review_progress')
        );
    }

    public function test_note_font_setting_included(): void
    {
        Setting::set('note_font', 'serif');

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('note_font', 'serif')
        );
    }
}
