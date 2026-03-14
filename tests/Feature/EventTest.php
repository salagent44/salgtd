<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_create_event(): void
    {
        $response = $this->post('/events', [
            'title' => 'Team Meeting',
            'event_date' => '2026-04-01',
            'event_time' => '10:00',
            'description' => 'Weekly standup',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Team Meeting',
            'description' => 'Weekly standup',
        ]);
    }

    public function test_can_update_event(): void
    {
        $event = CalendarEvent::create([
            'id' => Str::ulid(),
            'title' => 'Old Event',
            'event_date' => '2026-04-01',
        ]);

        $response = $this->put("/events/{$event->id}", [
            'title' => 'Updated Event',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'title' => 'Updated Event',
        ]);
    }

    public function test_can_move_event_date(): void
    {
        $event = CalendarEvent::create([
            'id' => Str::ulid(),
            'title' => 'Movable Event',
            'event_date' => '2026-04-01',
        ]);

        $response = $this->put("/events/{$event->id}/move", [
            'event_date' => '2026-04-15',
        ]);

        $response->assertRedirect();
        $event->refresh();
        $this->assertEquals('2026-04-15', $event->event_date->format('Y-m-d'));
    }

    public function test_can_create_multiday_event(): void
    {
        $response = $this->post('/events', [
            'title' => 'Conference',
            'event_date' => '2026-04-01',
            'end_date' => '2026-04-03',
            'event_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Conference',
            'end_date' => '2026-04-03',
            'end_time' => '17:00',
        ]);
    }

    public function test_can_create_recurring_event(): void
    {
        $response = $this->post('/events', [
            'title' => "Mom's Birthday",
            'event_date' => '2026-05-15',
            'recurrence' => 'yearly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', [
            'title' => "Mom's Birthday",
            'recurrence' => 'yearly',
        ]);
    }

    public function test_move_multiday_event_shifts_end_date(): void
    {
        $event = CalendarEvent::create([
            'id' => Str::ulid(),
            'title' => 'Trip',
            'event_date' => '2026-04-01',
            'end_date' => '2026-04-05',
        ]);

        $response = $this->put("/events/{$event->id}/move", [
            'event_date' => '2026-04-10',
        ]);

        $response->assertRedirect();
        $event->refresh();
        $this->assertEquals('2026-04-10', $event->event_date->format('Y-m-d'));
        $this->assertEquals('2026-04-14', $event->end_date->format('Y-m-d'));
    }

    public function test_can_delete_event(): void
    {
        $event = CalendarEvent::create([
            'id' => Str::ulid(),
            'title' => 'Delete me',
            'event_date' => '2026-04-01',
        ]);

        $response = $this->delete("/events/{$event->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('calendar_events', ['id' => $event->id]);
    }
}
