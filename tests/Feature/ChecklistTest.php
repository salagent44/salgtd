<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        \DB::table('sync_cursor')->insert(['version' => 0]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Test Task',
            'status' => 'next-action',
        ]);
    }

    public function test_can_add_checklist_item(): void
    {
        $response = $this->post("/items/{$this->item->id}/checklist", [
            'title' => 'Step 1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('checklist_items', [
            'item_id' => $this->item->id,
            'title' => 'Step 1',
            'completed' => false,
            'sort_order' => 0,
        ]);
    }

    public function test_checklist_items_auto_increment_sort_order(): void
    {
        $this->post("/items/{$this->item->id}/checklist", ['title' => 'Step 1']);
        $this->post("/items/{$this->item->id}/checklist", ['title' => 'Step 2']);
        $this->post("/items/{$this->item->id}/checklist", ['title' => 'Step 3']);

        $items = ChecklistItem::where('item_id', $this->item->id)->orderBy('sort_order')->get();
        $this->assertCount(3, $items);
        $this->assertEquals(0, $items[0]->sort_order);
        $this->assertEquals(1, $items[1]->sort_order);
        $this->assertEquals(2, $items[2]->sort_order);
    }

    public function test_can_toggle_checklist_item(): void
    {
        $ci = ChecklistItem::create([
            'id' => Str::ulid(),
            'item_id' => $this->item->id,
            'title' => 'Toggle me',
            'completed' => false,
        ]);

        $this->post("/checklist-items/{$ci->id}/toggle");
        $ci->refresh();
        $this->assertTrue($ci->completed);

        $this->post("/checklist-items/{$ci->id}/toggle");
        $ci->refresh();
        $this->assertFalse($ci->completed);
    }

    public function test_can_update_checklist_item_title(): void
    {
        $ci = ChecklistItem::create([
            'id' => Str::ulid(),
            'item_id' => $this->item->id,
            'title' => 'Original',
        ]);

        $this->put("/checklist-items/{$ci->id}", ['title' => 'Updated']);
        $ci->refresh();
        $this->assertEquals('Updated', $ci->title);
    }

    public function test_can_delete_checklist_item(): void
    {
        $ci = ChecklistItem::create([
            'id' => Str::ulid(),
            'item_id' => $this->item->id,
            'title' => 'Delete me',
        ]);

        $this->delete("/checklist-items/{$ci->id}");
        $this->assertSoftDeleted('checklist_items', ['id' => $ci->id]);
    }

    public function test_can_reorder_checklist_items(): void
    {
        $ci1 = ChecklistItem::create(['id' => Str::ulid(), 'item_id' => $this->item->id, 'title' => 'A', 'sort_order' => 0]);
        $ci2 = ChecklistItem::create(['id' => Str::ulid(), 'item_id' => $this->item->id, 'title' => 'B', 'sort_order' => 1]);
        $ci3 = ChecklistItem::create(['id' => Str::ulid(), 'item_id' => $this->item->id, 'title' => 'C', 'sort_order' => 2]);

        $response = $this->post("/items/{$this->item->id}/checklist/reorder", [
            'items' => [
                ['id' => (string) $ci3->id, 'sort_order' => 0],
                ['id' => (string) $ci1->id, 'sort_order' => 1],
                ['id' => (string) $ci2->id, 'sort_order' => 2],
            ],
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertEquals(1, $ci1->fresh()->sort_order);
        $this->assertEquals(2, $ci2->fresh()->sort_order);
        $this->assertEquals(0, $ci3->fresh()->sort_order);
    }

    public function test_checklist_requires_title(): void
    {
        $response = $this->post("/items/{$this->item->id}/checklist", ['title' => '']);
        $response->assertSessionHasErrors('title');
    }

    public function test_checklist_title_max_length(): void
    {
        $response = $this->post("/items/{$this->item->id}/checklist", [
            'title' => str_repeat('a', 501),
        ]);
        $response->assertSessionHasErrors('title');
    }

    public function test_checklist_items_loaded_with_item(): void
    {
        ChecklistItem::create(['id' => Str::ulid(), 'item_id' => $this->item->id, 'title' => 'Step 1', 'sort_order' => 0]);
        ChecklistItem::create(['id' => Str::ulid(), 'item_id' => $this->item->id, 'title' => 'Step 2', 'sort_order' => 1]);

        $item = Item::with('checklistItems')->find($this->item->id);
        $this->assertCount(2, $item->checklistItems);
        $this->assertEquals('Step 1', $item->checklistItems[0]->title);
        $this->assertEquals('Step 2', $item->checklistItems[1]->title);
    }

    public function test_sync_version_increments_on_checklist_create(): void
    {
        $this->post("/items/{$this->item->id}/checklist", ['title' => 'Step']);

        $ci = ChecklistItem::where('item_id', $this->item->id)->first();
        $this->assertGreaterThan(0, $ci->sync_version);
    }

    public function test_full_sync_includes_checklist_items(): void
    {
        $ci = ChecklistItem::create([
            'id' => Str::ulid(),
            'item_id' => $this->item->id,
            'title' => 'Sync step',
            'sort_order' => 0,
        ]);

        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sync/full');

        $response->assertOk();
        $checklistItems = $response->json('checklist_items');
        $this->assertCount(1, $checklistItems);
        $this->assertEquals($ci->id, $checklistItems[0]['id']);
        $this->assertEquals('Sync step', $checklistItems[0]['title']);
        $this->assertFalse($checklistItems[0]['completed']);
    }

    public function test_pull_returns_changed_checklist_items(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $versionBefore = \DB::table('sync_cursor')->value('version');

        $ci = ChecklistItem::create([
            'id' => Str::ulid(),
            'item_id' => $this->item->id,
            'title' => 'Pull step',
            'sort_order' => 0,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sync/pull', ['since_version' => $versionBefore]);

        $response->assertOk();
        $checklistItems = $response->json('checklist_items');
        $this->assertTrue(collect($checklistItems)->contains('id', $ci->id));
    }

    public function test_push_can_create_checklist_item(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;
        $newId = Str::ulid();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sync/push', [
                'mutations' => [[
                    'entity' => 'checklist_item',
                    'action' => 'upsert',
                    'id' => $newId,
                    'data' => [
                        'item_id' => $this->item->id,
                        'title' => 'Pushed step',
                        'completed' => false,
                        'sort_order' => 0,
                    ],
                ]],
            ]);

        $response->assertOk();
        $this->assertEquals('created', $response->json('results.0.status'));
        $this->assertDatabaseHas('checklist_items', ['id' => $newId, 'title' => 'Pushed step']);
    }

    public function test_can_create_checklist_type_item(): void
    {
        $response = $this->post('/items', [
            'title' => 'My Checklist',
            'status' => 'checklist',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'title' => 'My Checklist',
            'status' => 'checklist',
        ]);
    }

    public function test_can_update_checklist_title(): void
    {
        $checklist = Item::create([
            'id' => Str::ulid(),
            'title' => 'Old Title',
            'status' => 'checklist',
        ]);

        $response = $this->put("/items/{$checklist->id}", [
            'title' => 'New Title',
        ]);

        $response->assertRedirect();
        $this->assertEquals('New Title', $checklist->fresh()->title);
    }

    public function test_checklist_stays_checklist_after_title_update(): void
    {
        $checklist = Item::create([
            'id' => Str::ulid(),
            'title' => 'My Checklist',
            'status' => 'checklist',
        ]);

        $this->put("/items/{$checklist->id}", ['title' => 'Updated']);

        $this->assertEquals('checklist', $checklist->fresh()->status);
    }

    public function test_can_delete_checklist(): void
    {
        $checklist = Item::create([
            'id' => Str::ulid(),
            'title' => 'Delete Me',
            'status' => 'checklist',
        ]);

        $response = $this->delete("/items/{$checklist->id}");
        $response->assertRedirect();
        $this->assertSoftDeleted('items', ['id' => $checklist->id]);
    }

    public function test_deleting_checklist_soft_deletes_steps(): void
    {
        $checklist = Item::create([
            'id' => Str::ulid(),
            'title' => 'Checklist',
            'status' => 'checklist',
        ]);

        $ci1 = ChecklistItem::create(['id' => Str::ulid(), 'item_id' => $checklist->id, 'title' => 'Step 1', 'sort_order' => 0]);
        $ci2 = ChecklistItem::create(['id' => Str::ulid(), 'item_id' => $checklist->id, 'title' => 'Step 2', 'sort_order' => 1]);

        $this->delete("/items/{$checklist->id}");

        $this->assertSoftDeleted('items', ['id' => $checklist->id]);
    }

    public function test_checklist_can_be_marked_done(): void
    {
        $checklist = Item::create([
            'id' => Str::ulid(),
            'title' => 'Finish Me',
            'status' => 'checklist',
        ]);

        ChecklistItem::create(['id' => Str::ulid(), 'item_id' => $checklist->id, 'title' => 'Step 1', 'completed' => true, 'sort_order' => 0]);
        ChecklistItem::create(['id' => Str::ulid(), 'item_id' => $checklist->id, 'title' => 'Step 2', 'completed' => true, 'sort_order' => 1]);

        $response = $this->post("/items/{$checklist->id}/process", ['status' => 'done']);
        $response->assertRedirect();
        $this->assertEquals('done', $checklist->fresh()->status);
    }

    public function test_push_can_delete_checklist_item(): void
    {
        $ci = ChecklistItem::create([
            'id' => Str::ulid(),
            'item_id' => $this->item->id,
            'title' => 'Delete via push',
            'sort_order' => 0,
        ]);

        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sync/push', [
                'mutations' => [[
                    'entity' => 'checklist_item',
                    'action' => 'delete',
                    'id' => $ci->id,
                    'base_version' => $ci->sync_version,
                ]],
            ]);

        $response->assertOk();
        $this->assertEquals('applied', $response->json('results.0.status'));
        $this->assertSoftDeleted('checklist_items', ['id' => $ci->id]);
    }
}
