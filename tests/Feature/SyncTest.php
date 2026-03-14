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

class SyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed sync_cursor
        \DB::table('sync_cursor')->insert(['version' => 0]);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    protected function apiHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // === HasSyncVersion Trait Tests ===

    public function test_sync_version_increments_on_item_create(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Test item',
            'status' => 'inbox',
        ]);

        $this->assertGreaterThan(0, $item->sync_version);
    }

    public function test_sync_version_increments_on_item_update(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Test item',
            'status' => 'inbox',
        ]);

        $v1 = $item->sync_version;
        $item->update(['title' => 'Updated']);
        $this->assertGreaterThan($v1, $item->fresh()->sync_version);
    }

    public function test_sync_version_increments_on_soft_delete(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Test item',
            'status' => 'inbox',
        ]);

        $v1 = $item->sync_version;
        $item->delete();
        $this->assertNotNull($item->fresh()->deleted_at);
        $this->assertGreaterThan($v1, $item->fresh()->sync_version);
    }

    public function test_global_version_is_monotonic(): void
    {
        $item1 = Item::create(['id' => Str::ulid(), 'title' => 'One', 'status' => 'inbox']);
        $item2 = Item::create(['id' => Str::ulid(), 'title' => 'Two', 'status' => 'inbox']);
        $note = Note::create(['id' => Str::ulid(), 'title' => 'Note', 'content' => '']);

        $this->assertGreaterThan($item1->sync_version, $item2->sync_version);
        $this->assertGreaterThan($item2->sync_version, $note->sync_version);
    }

    // === Auth Tests ===

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'bad@example.com',
            'password' => 'wrong',
        ]);

        $response->assertUnprocessable();
    }

    public function test_logout_revokes_token(): void
    {
        $response = $this->postJson('/api/auth/logout', [], $this->apiHeaders());
        $response->assertOk();

        // Verify token was deleted from database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // === Full Sync ===

    public function test_full_sync_returns_all_data(): void
    {
        Item::create(['id' => Str::ulid(), 'title' => 'Item 1', 'status' => 'inbox']);
        Note::create(['id' => Str::ulid(), 'title' => 'Note 1', 'content' => 'body']);
        CalendarEvent::create([
            'id' => Str::ulid(),
            'title' => 'Event 1',
            'event_date' => '2026-03-15',
        ]);
        Context::create(['name' => 'Home', 'built_in' => false]);

        $response = $this->getJson('/api/sync/full', $this->apiHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonCount(1, 'notes')
            ->assertJsonCount(1, 'calendar_events')
            ->assertJsonCount(1, 'contexts');

        $this->assertGreaterThan(0, $response->json('version'));
    }

    public function test_full_sync_includes_soft_deleted(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Deleted', 'status' => 'inbox']);
        $item->delete();

        $response = $this->getJson('/api/sync/full', $this->apiHeaders());
        $response->assertOk()->assertJsonCount(1, 'items');
        $this->assertTrue($response->json('items.0.deleted'));
    }

    public function test_full_sync_requires_auth(): void
    {
        $this->getJson('/api/sync/full')->assertUnauthorized();
    }

    // === Pull Sync ===

    public function test_pull_returns_only_changed_records(): void
    {
        $item1 = Item::create(['id' => Str::ulid(), 'title' => 'Before', 'status' => 'inbox']);
        $sinceVersion = $item1->sync_version;

        $item2 = Item::create(['id' => Str::ulid(), 'title' => 'After', 'status' => 'inbox']);

        $response = $this->postJson('/api/sync/pull', [
            'since_version' => $sinceVersion,
        ], $this->apiHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'items');
        $this->assertEquals('After', $response->json('items.0.title'));
    }

    public function test_pull_includes_soft_deleted_records(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'To delete', 'status' => 'inbox']);
        $sinceVersion = $item->sync_version;

        $item->delete();

        $response = $this->postJson('/api/sync/pull', [
            'since_version' => $sinceVersion,
        ], $this->apiHeaders());

        $response->assertOk()->assertJsonCount(1, 'items');
        $this->assertTrue($response->json('items.0.deleted'));
    }

    public function test_pull_returns_empty_when_no_changes(): void
    {
        Item::create(['id' => Str::ulid(), 'title' => 'Existing', 'status' => 'inbox']);
        $currentVersion = (int) \DB::table('sync_cursor')->value('version');

        $response = $this->postJson('/api/sync/pull', [
            'since_version' => $currentVersion,
        ], $this->apiHeaders());

        $response->assertOk()
            ->assertJsonCount(0, 'items')
            ->assertJsonCount(0, 'notes');
    }

    // === Push Sync ===

    public function test_push_creates_new_item(): void
    {
        $id = (string) Str::ulid();

        $response = $this->postJson('/api/sync/push', [
            'mutations' => [
                [
                    'entity' => 'item',
                    'action' => 'upsert',
                    'id' => $id,
                    'data' => ['title' => 'From mobile', 'status' => 'inbox'],
                ],
            ],
        ], $this->apiHeaders());

        $response->assertOk();
        $this->assertEquals('created', $response->json('results.0.status'));
        $this->assertDatabaseHas('items', ['id' => $id, 'title' => 'From mobile']);
    }

    public function test_push_updates_existing_item(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Original', 'status' => 'inbox']);

        $response = $this->postJson('/api/sync/push', [
            'mutations' => [
                [
                    'entity' => 'item',
                    'action' => 'upsert',
                    'id' => $item->id,
                    'base_version' => $item->sync_version,
                    'data' => ['title' => 'Updated from mobile'],
                ],
            ],
        ], $this->apiHeaders());

        $response->assertOk();
        $this->assertEquals('applied', $response->json('results.0.status'));
        $this->assertEquals('Updated from mobile', $item->fresh()->title);
    }

    public function test_push_detects_conflict(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Original', 'status' => 'inbox']);
        $oldVersion = $item->sync_version;

        // Simulate server-side change
        $item->update(['title' => 'Server update']);

        $response = $this->postJson('/api/sync/push', [
            'mutations' => [
                [
                    'entity' => 'item',
                    'action' => 'upsert',
                    'id' => $item->id,
                    'base_version' => $oldVersion,
                    'data' => ['title' => 'Mobile update'],
                ],
            ],
        ], $this->apiHeaders());

        $response->assertOk();
        $this->assertEquals('conflict_overwritten', $response->json('results.0.status'));
        // Last-write-wins: mobile update should be applied
        $this->assertEquals('Mobile update', $item->fresh()->title);
    }

    public function test_push_soft_deletes_item(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'To delete', 'status' => 'inbox']);

        $response = $this->postJson('/api/sync/push', [
            'mutations' => [
                [
                    'entity' => 'item',
                    'action' => 'delete',
                    'id' => $item->id,
                    'base_version' => $item->sync_version,
                ],
            ],
        ], $this->apiHeaders());

        $response->assertOk();
        $this->assertEquals('applied', $response->json('results.0.status'));
        $this->assertSoftDeleted('items', ['id' => $item->id]);
    }

    public function test_push_creates_note_with_tags(): void
    {
        $id = (string) Str::ulid();

        $response = $this->postJson('/api/sync/push', [
            'mutations' => [
                [
                    'entity' => 'note',
                    'action' => 'upsert',
                    'id' => $id,
                    'data' => [
                        'title' => 'Tagged note',
                        'content' => 'body',
                        'tags' => ['work', 'important'],
                    ],
                ],
            ],
        ], $this->apiHeaders());

        $response->assertOk();
        $note = Note::find($id);
        $this->assertNotNull($note);
        $this->assertCount(2, $note->tags);
    }

    public function test_push_multiple_mutations(): void
    {
        $id1 = (string) Str::ulid();
        $id2 = (string) Str::ulid();

        $response = $this->postJson('/api/sync/push', [
            'mutations' => [
                [
                    'entity' => 'item',
                    'action' => 'upsert',
                    'id' => $id1,
                    'data' => ['title' => 'Item 1', 'status' => 'inbox'],
                ],
                [
                    'entity' => 'note',
                    'action' => 'upsert',
                    'id' => $id2,
                    'data' => ['title' => 'Note 1', 'content' => ''],
                ],
            ],
        ], $this->apiHeaders());

        $response->assertOk()->assertJsonCount(2, 'results');
        $this->assertDatabaseHas('items', ['id' => $id1]);
        $this->assertDatabaseHas('notes', ['id' => $id2]);
    }

    public function test_push_restores_soft_deleted_record_on_upsert(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Deleted', 'status' => 'inbox']);
        $item->delete();

        $response = $this->postJson('/api/sync/push', [
            'mutations' => [
                [
                    'entity' => 'item',
                    'action' => 'upsert',
                    'id' => $item->id,
                    'data' => ['title' => 'Restored', 'status' => 'inbox'],
                ],
            ],
        ], $this->apiHeaders());

        $response->assertOk();
        $fresh = Item::find($item->id);
        $this->assertNotNull($fresh);
        $this->assertEquals('Restored', $fresh->title);
        $this->assertNull($fresh->deleted_at);
    }

    // === API Item Endpoints ===

    public function test_api_process_item(): void
    {
        $item = Item::create(['id' => Str::ulid(), 'title' => 'Inbox item', 'status' => 'inbox']);

        $response = $this->postJson("/api/items/{$item->id}/process", [
            'status' => 'next-action',
            'context' => 'Work',
        ], $this->apiHeaders());

        $response->assertOk()->assertJsonPath('item.status', 'next-action');
    }

    public function test_api_move_to_inbox(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Waiting item',
            'status' => 'waiting',
            'waiting_for' => 'John',
        ]);

        $response = $this->postJson("/api/items/{$item->id}/move-to-inbox", [], $this->apiHeaders());

        $response->assertOk()->assertJsonPath('item.status', 'inbox');
        $this->assertNull($item->fresh()->waiting_for);
    }

    // === Broadcast Tests ===

    public function test_sync_updated_event_structure(): void
    {
        $event = new \App\Events\SyncUpdated(
            entity: 'item',
            id: 'test-id',
            syncVersion: 42,
        );

        $this->assertEquals('item', $event->entity);
        $this->assertEquals('test-id', $event->id);
        $this->assertEquals(42, $event->syncVersion);
        $this->assertEquals('SyncUpdated', $event->broadcastAs());

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $channels[0]);
    }
}
