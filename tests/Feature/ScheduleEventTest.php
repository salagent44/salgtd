<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_scheduling_creates_calendar_event(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Dentist Appointment',
            'status' => 'inbox',
        ]);

        $response = $this->post("/items/{$item->id}/schedule-event", [
            'event_date' => '2026-04-15',
            'event_time' => '10:00',
            'color' => 'red',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Dentist Appointment',
            'event_date' => '2026-04-15',
            'event_time' => '10:00',
            'color' => 'red',
        ]);
    }

    public function test_item_is_marked_done_after_scheduling(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Team Standup',
            'status' => 'inbox',
        ]);

        $this->post("/items/{$item->id}/schedule-event", [
            'event_date' => '2026-04-15',
        ]);

        $item->refresh();
        $this->assertEquals('done', $item->status);
        $this->assertEquals('inbox', $item->original_status);
        $this->assertNotNull($item->completed_at);
    }

    public function test_event_date_is_required(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Missing Date',
            'status' => 'inbox',
        ]);

        $response = $this->post("/items/{$item->id}/schedule-event", []);

        $response->assertSessionHasErrors('event_date');
    }

    public function test_auth_required(): void
    {
        // Log out
        $this->app['auth']->forgetGuards();

        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Secret Event',
            'status' => 'inbox',
        ]);

        $response = $this->post("/items/{$item->id}/schedule-event", [
            'event_date' => '2026-04-15',
        ]);

        $response->assertRedirect('/login');
    }
}
