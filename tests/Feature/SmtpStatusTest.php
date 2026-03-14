<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_smtp_status_returns_json(): void
    {
        $response = $this->getJson('/api/smtp-status');

        $response->assertOk();
        $response->assertJsonStructure(['up']);
    }

    public function test_smtp_status_returns_up_when_server_running(): void
    {
        // The SMTP server should be running on port 25 in this environment
        $conn = @fsockopen('127.0.0.1', 25, $errno, $errstr, 2);
        if (!$conn) {
            $this->markTestSkipped('SMTP server not running on port 25');
        }
        fclose($conn);

        $response = $this->getJson('/api/smtp-status');

        $response->assertOk();
        $response->assertJson(['up' => true]);
    }

    public function test_smtp_status_returns_down_when_server_not_running(): void
    {
        // Point at a port nothing listens on
        config(['services.smtp.port' => 59999]);

        $response = $this->getJson('/api/smtp-status');

        $response->assertOk();
        $response->assertJson(['up' => false]);
    }

    public function test_smtp_status_requires_auth(): void
    {
        auth()->logout();

        $response = $this->getJson('/api/smtp-status');

        $response->assertUnauthorized();
    }
}
