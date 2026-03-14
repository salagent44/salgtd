<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_update_setting(): void
    {
        $response = $this->put('/settings/theme', [
            'value' => 'dark',
        ]);

        $response->assertRedirect();
        $this->assertEquals('dark', Setting::get('theme'));
    }

    public function test_can_save_email_address(): void
    {
        $response = $this->put('/settings/email_address', [
            'value' => 'sal@gmail.com',
        ]);

        $response->assertRedirect();
        $this->assertEquals('sal@gmail.com', Setting::get('email_address'));
    }

    public function test_dashboard_includes_email_address_setting(): void
    {
        Setting::set('email_address', 'sal@gmail.com');

        $response = $this->get('/');

        $response->assertOk();
        $props = $response->original->getData()['page']['props'];
        $this->assertEquals('sal@gmail.com', $props['email_address']);
    }

    public function test_can_clear_email_address(): void
    {
        Setting::set('email_address', 'sal@gmail.com');

        $response = $this->put('/settings/email_address', [
            'value' => null,
        ]);

        $response->assertRedirect();
        $this->assertNull(Setting::get('email_address'));
    }

    public function test_dashboard_loads_with_theme(): void
    {
        Setting::set('theme', 'dark');

        $response = $this->get('/');

        $response->assertOk();
    }
}
