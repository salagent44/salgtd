<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_dashboard_loads_items_with_email_relationship(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Email item',
            'status' => 'inbox',
        ]);

        Email::create([
            'id' => Str::ulid(),
            'item_id' => $item->id,
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'to_address' => 'sal@salmaster.dev',
            'subject' => 'Test email subject',
            'body_text' => 'This is a test email body.',
            'received_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $items = $response->original->getData()['page']['props']['items'];
        $emailItem = collect($items)->firstWhere('id', $item->id);
        $this->assertNotNull($emailItem['email']);
        $this->assertEquals('test@example.com', $emailItem['email']['from_address']);
    }

    public function test_email_linked_item_can_be_clarified(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Clarify this email',
            'status' => 'inbox',
        ]);

        $email = Email::create([
            'id' => Str::ulid(),
            'item_id' => $item->id,
            'from_address' => 'sender@example.com',
            'from_name' => 'Sender',
            'to_address' => 'sal@salmaster.dev',
            'subject' => 'Clarify this email',
            'body_text' => 'Body text here.',
            'received_at' => now(),
        ]);

        $response = $this->post("/items/{$item->id}/process", [
            'status' => 'next-action',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('next-action', $item->status);

        $email->refresh();
        $this->assertEquals($item->id, $email->item_id);
    }

    public function test_deleting_item_sets_email_item_id_to_null(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Delete me',
            'status' => 'inbox',
        ]);

        $email = Email::create([
            'id' => Str::ulid(),
            'item_id' => $item->id,
            'from_address' => 'sender@example.com',
            'from_name' => 'Sender',
            'to_address' => 'sal@salmaster.dev',
            'subject' => 'Delete me',
            'body_text' => 'Body.',
            'received_at' => now(),
        ]);

        $this->delete("/items/{$item->id}");

        $this->assertSoftDeleted('items', ['id' => $item->id]);
        // Email record still exists since item is soft-deleted
        $this->assertDatabaseHas('emails', ['id' => $email->id]);
    }

    public function test_non_email_items_have_null_email(): void
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'title' => 'Regular item',
            'status' => 'inbox',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $items = $response->original->getData()['page']['props']['items'];
        $regularItem = collect($items)->firstWhere('id', $item->id);
        $this->assertNull($regularItem['email']);
    }
}
