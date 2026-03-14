<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_status_returns_idle_by_default(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/update-status');

        $response->assertOk()
            ->assertJson([
                'status' => 'idle',
                'triggered_at' => null,
            ]);
    }

    public function test_trigger_sets_status_to_triggered(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/update-trigger');

        $response->assertOk()
            ->assertJson(['triggered' => true]);

        $this->assertEquals('triggered', Setting::get('update_status'));
        $this->assertNotNull(Setting::get('update_triggered_at'));
    }

    public function test_trigger_rejected_when_already_triggered(): void
    {
        Setting::set('update_status', 'triggered');

        $response = $this->actingAs($this->user)
            ->postJson('/api/update-trigger');

        $response->assertOk()
            ->assertJson(['triggered' => false]);
    }

    public function test_trigger_rejected_when_updating(): void
    {
        Setting::set('update_status', 'updating');

        $response = $this->actingAs($this->user)
            ->postJson('/api/update-trigger');

        $response->assertOk()
            ->assertJson(['triggered' => false]);
    }

    public function test_trigger_allowed_after_done(): void
    {
        Setting::set('update_status', 'done');

        $response = $this->actingAs($this->user)
            ->postJson('/api/update-trigger');

        $response->assertOk()
            ->assertJson(['triggered' => true]);

        $this->assertEquals('triggered', Setting::get('update_status'));
    }

    public function test_trigger_allowed_after_error(): void
    {
        Setting::set('update_status', 'error');

        $response = $this->actingAs($this->user)
            ->postJson('/api/update-trigger');

        $response->assertOk()
            ->assertJson(['triggered' => true]);
    }

    public function test_status_reflects_current_state(): void
    {
        Setting::set('update_status', 'updating');
        Setting::set('update_triggered_at', '2026-03-14T10:00:00+00:00');

        $response = $this->actingAs($this->user)
            ->getJson('/api/update-status');

        $response->assertOk()
            ->assertJson([
                'status' => 'updating',
                'triggered_at' => '2026-03-14T10:00:00+00:00',
            ]);
    }

    public function test_status_requires_auth(): void
    {
        $this->getJson('/api/update-status')
            ->assertUnauthorized();
    }

    public function test_trigger_requires_auth(): void
    {
        $this->postJson('/api/update-trigger')
            ->assertUnauthorized();
    }

    public function test_artisan_update_status_command(): void
    {
        $this->artisan('update:status', ['status' => 'updating'])
            ->assertExitCode(0);

        $this->assertEquals('updating', Setting::get('update_status'));
    }

    public function test_artisan_update_status_done(): void
    {
        $this->artisan('update:status', ['status' => 'done'])
            ->assertExitCode(0);

        $this->assertEquals('done', Setting::get('update_status'));
    }

    public function test_artisan_update_status_error(): void
    {
        $this->artisan('update:status', ['status' => 'error'])
            ->assertExitCode(0);

        $this->assertEquals('error', Setting::get('update_status'));
    }

    public function test_artisan_update_status_idle(): void
    {
        Setting::set('update_status', 'done');

        $this->artisan('update:status', ['status' => 'idle'])
            ->assertExitCode(0);

        $this->assertEquals('idle', Setting::get('update_status'));
    }

    public function test_artisan_update_status_rejects_invalid(): void
    {
        $this->artisan('update:status', ['status' => 'bogus'])
            ->assertExitCode(1);
    }

    public function test_full_update_lifecycle(): void
    {
        // 1. Start idle
        $this->assertEquals('idle', Setting::get('update_status', 'idle'));

        // 2. Trigger
        $this->actingAs($this->user)
            ->postJson('/api/update-trigger')
            ->assertJson(['triggered' => true]);
        $this->assertEquals('triggered', Setting::get('update_status'));

        // 3. Can't trigger again
        $this->actingAs($this->user)
            ->postJson('/api/update-trigger')
            ->assertJson(['triggered' => false]);

        // 4. Cron sets updating
        $this->artisan('update:status', ['status' => 'updating']);
        $this->assertEquals('updating', Setting::get('update_status'));

        // 5. Still can't trigger
        $this->actingAs($this->user)
            ->postJson('/api/update-trigger')
            ->assertJson(['triggered' => false]);

        // 6. Cron sets done
        $this->artisan('update:status', ['status' => 'done']);
        $this->assertEquals('done', Setting::get('update_status'));

        // 7. Status API reflects done
        $this->actingAs($this->user)
            ->getJson('/api/update-status')
            ->assertJson(['status' => 'done']);

        // 8. Can trigger again
        $this->actingAs($this->user)
            ->postJson('/api/update-trigger')
            ->assertJson(['triggered' => true]);
    }
}
