<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Health endpoint returns ok when server is reachable.
     */
    public function test_health_returns_ok_when_online(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    /**
     * Health endpoint does not require auth — available to all clients.
     */
    public function test_health_does_not_require_auth(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
    }

    /**
     * When server is up, item creation works normally.
     */
    public function test_can_create_item_when_online(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/items', [
            'title' => 'Online task',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['title' => 'Online task']);
    }

    /**
     * When server is up, item processing works normally.
     */
    public function test_can_process_item_when_online(): void
    {
        $user = User::factory()->create();
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Process me',
            'status' => 'inbox',
        ]);

        $response = $this->actingAs($user)->post("/items/{$item->id}/process", [
            'status' => 'next-action',
            'context' => '@computer',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'next-action',
            'context' => '@computer',
        ]);
    }

    /**
     * When server is up, item deletion works normally.
     */
    public function test_can_delete_item_when_online(): void
    {
        $user = User::factory()->create();
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Delete me',
            'status' => 'inbox',
        ]);

        $response = $this->actingAs($user)->delete("/items/{$item->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    /**
     * Health endpoint is fast (under 500ms) for polling viability.
     */
    public function test_health_responds_quickly(): void
    {
        $start = microtime(true);
        $this->getJson('/health');
        $duration = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500, $duration, 'Health endpoint should respond in under 500ms');
    }

    /**
     * Dashboard loads with items prop available (frontend needs this to render).
     */
    public function test_dashboard_provides_items_for_offline_guard(): void
    {
        $user = User::factory()->create();
        Item::create([
            'id' => Str::ulid(),
            'title' => 'Existing task',
            'status' => 'next-action',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('items')
        );
    }

    /**
     * Auth-required endpoints reject unauthenticated requests.
     * This verifies that even if the frontend guard is bypassed,
     * the backend still requires auth for mutations.
     */
    public function test_mutations_require_auth_as_backend_guard(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Protected',
            'status' => 'inbox',
        ]);

        // POST create
        $this->post('/items', ['title' => 'Sneaky'])->assertRedirect('/login');

        // POST process
        $this->post("/items/{$item->id}/process", ['status' => 'done'])->assertRedirect('/login');

        // PUT update
        $this->put("/items/{$item->id}", ['title' => 'Hacked'])->assertRedirect('/login');

        // DELETE
        $this->delete("/items/{$item->id}")->assertRedirect('/login');

        // Item should be untouched
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'title' => 'Protected',
            'status' => 'inbox',
        ]);
    }
}
