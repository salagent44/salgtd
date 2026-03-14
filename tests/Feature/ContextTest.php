<?php

namespace Tests\Feature;

use App\Models\Context;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_create_context(): void
    {
        $response = $this->post('/contexts', [
            'name' => '@errands',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contexts', [
            'name' => '@errands',
            'built_in' => false,
        ]);
    }

    public function test_cannot_delete_builtin_context(): void
    {
        $context = Context::create([
            'name' => '@home',
            'built_in' => true,
        ]);

        $response = $this->delete("/contexts/{$context->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('contexts', ['id' => $context->id]);
    }

    public function test_can_delete_custom_context(): void
    {
        $context = Context::create([
            'name' => '@custom',
            'built_in' => false,
        ]);

        $response = $this->delete("/contexts/{$context->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('contexts', ['id' => $context->id]);
    }
}
