<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_save_review_progress(): void
    {
        $progress = json_encode(['checked' => ['collect' => true]]);

        $response = $this->actingAs($this->user)
            ->put('/settings/review_progress', ['value' => $progress]);

        $response->assertRedirect();
        $this->assertEquals($progress, Setting::get('review_progress'));
    }

    public function test_can_clear_review_progress(): void
    {
        Setting::set('review_progress', json_encode(['checked' => ['collect' => true]]));

        $response = $this->actingAs($this->user)
            ->put('/settings/review_progress', ['value' => null]);

        $response->assertRedirect();
        $this->assertNull(Setting::get('review_progress'));
    }

    public function test_can_set_last_review_date(): void
    {
        $date = now()->toISOString();

        $response = $this->actingAs($this->user)
            ->put('/settings/last_review', ['value' => $date]);

        $response->assertRedirect();
        $this->assertEquals($date, Setting::get('last_review'));
    }

    public function test_complete_review_clears_progress_and_sets_date(): void
    {
        // Simulate in-progress review
        $progress = json_encode(['checked' => ['collect' => true, 'inbox' => true]]);
        Setting::set('review_progress', $progress);

        // Complete: clear progress
        $this->actingAs($this->user)
            ->put('/settings/review_progress', ['value' => null]);

        // Complete: set last_review date
        $date = now()->toISOString();
        $this->actingAs($this->user)
            ->put('/settings/last_review', ['value' => $date]);

        $this->assertNull(Setting::get('review_progress'));
        $this->assertEquals($date, Setting::get('last_review'));
    }

    public function test_starting_new_review_after_completion(): void
    {
        // Complete a review
        $date = now()->toISOString();
        $this->actingAs($this->user)
            ->put('/settings/last_review', ['value' => $date]);
        $this->actingAs($this->user)
            ->put('/settings/review_progress', ['value' => null]);

        // Verify clean slate
        $this->assertNull(Setting::get('review_progress'));

        // Start new review by checking an item
        $newProgress = json_encode(['checked' => ['collect' => true]]);
        $this->actingAs($this->user)
            ->put('/settings/review_progress', ['value' => $newProgress]);

        $this->assertEquals($newProgress, Setting::get('review_progress'));

        // Dashboard should reflect in-progress state
        $response = $this->actingAs($this->user)->get('/');
        $response->assertInertia(fn ($page) => $page
            ->where('review_progress', $newProgress)
            ->where('last_review', $date)
        );
    }

    public function test_dashboard_includes_review_progress(): void
    {
        $progress = json_encode(['checked' => ['inbox' => true]]);
        Setting::set('review_progress', $progress);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('review_progress', $progress)
        );
    }

    public function test_dashboard_includes_null_review_progress_when_cleared(): void
    {
        // Ensure no progress exists
        Setting::set('review_progress', null);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('review_progress', null)
        );
    }

    public function test_dashboard_includes_last_review(): void
    {
        $date = '2026-03-10T12:00:00.000Z';
        Setting::set('last_review', $date);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('last_review', $date)
        );
    }

    public function test_review_items_by_status_available_on_dashboard(): void
    {
        Item::create(['id' => Str::ulid(), 'title' => 'Inbox item', 'status' => 'inbox']);
        Item::create(['id' => Str::ulid(), 'title' => 'Next action', 'status' => 'next-action']);
        Item::create(['id' => Str::ulid(), 'title' => 'Project', 'status' => 'project']);
        Item::create(['id' => Str::ulid(), 'title' => 'Waiting', 'status' => 'waiting']);
        Item::create(['id' => Str::ulid(), 'title' => 'Someday', 'status' => 'someday']);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('items', 5)
        );
    }

    public function test_can_create_next_action_for_stuck_project_during_review(): void
    {
        $projectId = (string) Str::ulid();
        Item::create(['id' => $projectId, 'title' => 'My Project', 'status' => 'project']);

        $response = $this->actingAs($this->user)->post('/items', [
            'title' => 'First step',
            'status' => 'next-action',
            'project_id' => $projectId,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'title' => 'First step',
            'status' => 'next-action',
            'project_id' => $projectId,
        ]);
    }

    public function test_review_settings_require_auth(): void
    {
        $this->put('/settings/review_progress', ['value' => 'test'])
            ->assertRedirect('/login');

        $this->put('/settings/last_review', ['value' => 'test'])
            ->assertRedirect('/login');
    }

    public function test_overwriting_review_progress_replaces_previous(): void
    {
        $first = json_encode(['checked' => ['collect' => true]]);
        $this->actingAs($this->user)
            ->put('/settings/review_progress', ['value' => $first]);

        $second = json_encode(['checked' => ['collect' => true, 'inbox' => true]]);
        $this->actingAs($this->user)
            ->put('/settings/review_progress', ['value' => $second]);

        $this->assertEquals($second, Setting::get('review_progress'));
    }

    public function test_updating_last_review_overwrites_previous(): void
    {
        $first = '2026-03-01T12:00:00.000Z';
        $this->actingAs($this->user)
            ->put('/settings/last_review', ['value' => $first]);

        $second = '2026-03-08T12:00:00.000Z';
        $this->actingAs($this->user)
            ->put('/settings/last_review', ['value' => $second]);

        $this->assertEquals($second, Setting::get('last_review'));
    }
}
