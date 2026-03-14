<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.inbound_email.secret' => 'test-secret-123']);
    }

    public function test_inbound_email_creates_item_and_email(): void
    {
        $response = $this->postJson('/api/inbound-email', [
            'from' => 'sender@example.com',
            'from_name' => 'Test Sender',
            'to' => 'inbox@tasks.salmaster.dev',
            'subject' => 'Important email',
            'body' => 'This is the email body.',
            'message_id' => '<abc123@example.com>',
        ], ['X-Webhook-Secret' => 'test-secret-123']);

        $response->assertStatus(201);
        $response->assertJsonStructure(['status', 'email_id', 'item_id']);

        $this->assertDatabaseHas('items', [
            'title' => 'Important email',
            'status' => 'inbox',
        ]);

        $this->assertDatabaseHas('emails', [
            'from_address' => 'sender@example.com',
            'from_name' => 'Test Sender',
            'to_address' => 'inbox@tasks.salmaster.dev',
            'subject' => 'Important email',
            'body_text' => 'This is the email body.',
            'message_id' => '<abc123@example.com>',
        ]);
    }

    public function test_rejects_request_without_secret(): void
    {
        $response = $this->postJson('/api/inbound-email', [
            'from' => 'sender@example.com',
            'to' => 'inbox@tasks.salmaster.dev',
            'subject' => 'Hacker email',
            'body' => 'Should not work.',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('items', ['title' => 'Hacker email']);
    }

    public function test_rejects_request_with_wrong_secret(): void
    {
        $response = $this->postJson('/api/inbound-email', [
            'from' => 'sender@example.com',
            'to' => 'inbox@tasks.salmaster.dev',
            'subject' => 'Bad secret',
            'body' => 'Wrong key.',
        ], ['X-Webhook-Secret' => 'wrong-secret']);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('items', ['title' => 'Bad secret']);
    }

    public function test_deduplicates_by_message_id(): void
    {
        $this->postJson('/api/inbound-email', [
            'from' => 'sender@example.com',
            'to' => 'inbox@tasks.salmaster.dev',
            'subject' => 'First delivery',
            'body' => 'Body.',
            'message_id' => '<dupe@example.com>',
        ], ['X-Webhook-Secret' => 'test-secret-123']);

        $response = $this->postJson('/api/inbound-email', [
            'from' => 'sender@example.com',
            'to' => 'inbox@tasks.salmaster.dev',
            'subject' => 'First delivery',
            'body' => 'Body.',
            'message_id' => '<dupe@example.com>',
        ], ['X-Webhook-Secret' => 'test-secret-123']);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'duplicate']);

        $this->assertEquals(1, Email::where('message_id', '<dupe@example.com>')->count());
        $this->assertEquals(1, Item::where('title', 'First delivery')->count());
    }

    public function test_allows_email_without_message_id(): void
    {
        $response = $this->postJson('/api/inbound-email', [
            'from' => 'sender@example.com',
            'to' => 'inbox@tasks.salmaster.dev',
            'subject' => 'No message ID',
            'body' => 'Still works.',
        ], ['X-Webhook-Secret' => 'test-secret-123']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('emails', [
            'subject' => 'No message ID',
            'message_id' => null,
        ]);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/inbound-email', [
            'from' => 'sender@example.com',
        ], ['X-Webhook-Secret' => 'test-secret-123']);

        $response->assertStatus(422);
    }

    public function test_new_email_item_has_email_relationship_on_dashboard(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/inbound-email', [
            'from' => 'boss@company.com',
            'from_name' => 'The Boss',
            'to' => 'inbox@tasks.salmaster.dev',
            'subject' => 'Urgent: Review needed',
            'body' => 'Please review ASAP.',
        ], ['X-Webhook-Secret' => 'test-secret-123']);

        $response = $this->get('/');
        $response->assertOk();

        $items = $response->original->getData()['page']['props']['items'];
        $emailItem = collect($items)->firstWhere('title', 'Urgent: Review needed');
        $this->assertNotNull($emailItem);
        $this->assertNotNull($emailItem['email']);
        $this->assertEquals('boss@company.com', $emailItem['email']['from_address']);
    }
}
