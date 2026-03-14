<?php

namespace Tests\Feature;

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

    protected function tearDown(): void
    {
        // Clean up any test files
        @unlink('/data/build-ready');
        @unlink('/data/update-apply');
        parent::tearDown();
    }

    public function test_status_returns_no_build_ready_by_default(): void
    {
        @unlink('/data/build-ready');
        @unlink('/data/update-apply');

        $response = $this->actingAs($this->user)
            ->getJson('/api/update-status');

        $response->assertOk()
            ->assertJson([
                'build_ready' => false,
                'pending_commit' => null,
                'applying' => false,
            ]);
    }

    public function test_status_detects_build_ready(): void
    {
        @mkdir('/data', 0755, true);
        file_put_contents('/data/build-ready', 'abc1234');

        // Ensure COMMIT_HASH file has a different value
        $commitHashFile = base_path('COMMIT_HASH');
        $originalHash = @file_get_contents($commitHashFile);
        file_put_contents($commitHashFile, 'old1234');

        $response = $this->actingAs($this->user)
            ->getJson('/api/update-status');

        $response->assertOk()
            ->assertJson([
                'build_ready' => true,
                'pending_commit' => 'abc1234',
                'applying' => false,
            ]);

        // Restore original hash
        if ($originalHash !== false) {
            file_put_contents($commitHashFile, $originalHash);
        }
    }

    public function test_status_not_ready_when_same_commit(): void
    {
        @mkdir('/data', 0755, true);
        $commitHashFile = base_path('COMMIT_HASH');
        $currentHash = trim(@file_get_contents($commitHashFile) ?: 'unknown');
        file_put_contents('/data/build-ready', $currentHash);

        $response = $this->actingAs($this->user)
            ->getJson('/api/update-status');

        $response->assertOk()
            ->assertJson([
                'build_ready' => false,
            ]);
    }

    public function test_apply_creates_signal_file(): void
    {
        @mkdir('/data', 0755, true);
        file_put_contents('/data/build-ready', 'abc1234');
        @unlink('/data/update-apply');

        $response = $this->actingAs($this->user)
            ->postJson('/api/update-apply');

        $response->assertOk()
            ->assertJson(['applied' => true]);

        $this->assertFileExists('/data/update-apply');
    }

    public function test_apply_rejected_when_no_build_ready(): void
    {
        @unlink('/data/build-ready');

        $response = $this->actingAs($this->user)
            ->postJson('/api/update-apply');

        $response->assertOk()
            ->assertJson([
                'applied' => false,
                'reason' => 'No build ready',
            ]);
    }

    public function test_apply_rejected_when_already_applying(): void
    {
        @mkdir('/data', 0755, true);
        file_put_contents('/data/build-ready', 'abc1234');
        file_put_contents('/data/update-apply', date('c'));

        $response = $this->actingAs($this->user)
            ->postJson('/api/update-apply');

        $response->assertOk()
            ->assertJson([
                'applied' => false,
                'reason' => 'Already applying',
            ]);
    }

    public function test_status_shows_applying_state(): void
    {
        @mkdir('/data', 0755, true);
        file_put_contents('/data/build-ready', 'abc1234');
        file_put_contents('/data/update-apply', date('c'));

        $response = $this->actingAs($this->user)
            ->getJson('/api/update-status');

        $response->assertOk()
            ->assertJson([
                'applying' => true,
            ]);
    }

    public function test_status_requires_auth(): void
    {
        $this->getJson('/api/update-status')
            ->assertUnauthorized();
    }

    public function test_apply_requires_auth(): void
    {
        $this->postJson('/api/update-apply')
            ->assertUnauthorized();
    }
}
