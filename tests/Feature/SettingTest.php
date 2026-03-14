<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_update_setting(): void
    {
        $response = $this->put('/settings/theme', ['value' => 'dark']);

        $response->assertRedirect();
        $this->assertEquals('dark', Setting::get('theme'));
    }

    public function test_can_save_email_address(): void
    {
        $response = $this->put('/settings/email_address', ['value' => 'sal@gmail.com']);

        $response->assertRedirect();
        $this->assertEquals('sal@gmail.com', Setting::get('email_address'));
    }

    public function test_dashboard_includes_email_address_setting(): void
    {
        Setting::set('email_address', 'sal@gmail.com');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('email_address', 'sal@gmail.com')
        );
    }

    public function test_can_clear_email_address(): void
    {
        Setting::set('email_address', 'sal@gmail.com');

        $response = $this->put('/settings/email_address', ['value' => null]);

        $response->assertRedirect();
        $this->assertNull(Setting::get('email_address'));
    }

    public function test_dashboard_loads_with_theme(): void
    {
        Setting::set('theme', 'dark');

        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_disallowed_setting_key_returns_403(): void
    {
        $response = $this->put('/settings/admin_password', ['value' => 'hacked']);

        $response->assertForbidden();
    }

    public function test_invalid_theme_value_returns_403(): void
    {
        $response = $this->put('/settings/theme', ['value' => 'hacker-theme']);

        $response->assertForbidden();
    }

    public function test_setting_update_is_idempotent(): void
    {
        $this->put('/settings/theme', ['value' => 'dark']);
        $this->put('/settings/theme', ['value' => 'dark']);

        $this->assertEquals('dark', Setting::get('theme'));
        $this->assertEquals(1, Setting::where('key', 'theme')->count());
    }

    public function test_setting_set_null_removes_value(): void
    {
        Setting::set('note_font', 'inter');
        $this->assertEquals('inter', Setting::get('note_font'));

        $this->put('/settings/note_font', ['value' => null]);

        // Value should be null (row may still exist but value is null)
        $this->assertNull(Setting::get('note_font'));
    }

    public function test_setting_get_returns_default_when_missing(): void
    {
        $this->assertEquals('fallback', Setting::get('nonexistent', 'fallback'));
        $this->assertNull(Setting::get('nonexistent'));
    }

    public function test_rapid_setting_updates_all_persist(): void
    {
        $this->put('/settings/theme', ['value' => 'dark']);
        $this->put('/settings/note_font', ['value' => 'inter']);
        $this->put('/settings/email_address', ['value' => 'test@test.com']);

        $this->assertEquals('dark', Setting::get('theme'));
        $this->assertEquals('inter', Setting::get('note_font'));
        $this->assertEquals('test@test.com', Setting::get('email_address'));
    }

    public function test_settings_require_auth(): void
    {
        // Create a fresh client without auth
        $this->app['auth']->forgetGuards();

        $response = $this->call('PUT', '/settings/theme', ['value' => 'dark']);

        $response->assertRedirect('/login');
    }
}
