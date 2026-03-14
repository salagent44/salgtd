<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_2fa_status_returns_disabled_by_default(): void
    {
        $response = $this->getJson('/api/2fa/status');
        $response->assertOk()->assertJson(['enabled' => false]);
    }

    public function test_2fa_setup_returns_secret_and_qr(): void
    {
        $response = $this->postJson('/api/2fa/setup');

        $response->assertOk()
            ->assertJsonStructure(['secret', 'qr_svg']);

        $this->assertNotNull($response->json('secret'));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $response->json('qr_svg'));

        // Secret should be stored encrypted
        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_confirmed_at);
    }

    public function test_2fa_confirm_with_valid_code(): void
    {
        // Set up 2FA first
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ]);

        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/2fa/confirm', ['code' => $validCode]);

        $response->assertOk()->assertJson(['confirmed' => true]);

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_confirmed_at);
        $this->assertTrue($this->user->hasTwoFactorEnabled());
    }

    public function test_2fa_confirm_rejects_invalid_code(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->postJson('/api/2fa/confirm', ['code' => '000000']);

        $response->assertStatus(422)->assertJson(['error' => 'Invalid code']);
        $this->user->refresh();
        $this->assertNull($this->user->two_factor_confirmed_at);
    }

    public function test_2fa_disable_with_correct_password(): void
    {
        $this->user->update([
            'two_factor_secret' => encrypt('testsecret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/2fa/disable', ['password' => 'password']);

        $response->assertOk()->assertJson(['disabled' => true]);

        $this->user->refresh();
        $this->assertNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_confirmed_at);
        $this->assertFalse($this->user->hasTwoFactorEnabled());
    }

    public function test_2fa_disable_rejects_wrong_password(): void
    {
        $this->user->update([
            'two_factor_secret' => encrypt('testsecret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/2fa/disable', ['password' => 'wrongpassword']);

        $response->assertStatus(422)->assertJson(['error' => 'Incorrect password']);

        $this->user->refresh();
        $this->assertTrue($this->user->hasTwoFactorEnabled());
    }

    public function test_web_login_redirects_to_2fa_challenge(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Log out first so we can test the login flow
        auth()->logout();

        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('2fa.challenge'));
        $this->assertGuest();
    }

    public function test_web_login_skips_2fa_when_not_enabled(): void
    {
        auth()->logout();

        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_2fa_challenge_page_requires_session(): void
    {
        auth()->logout();

        $response = $this->get(route('2fa.challenge'));
        $response->assertRedirect(route('login'));
    }

    public function test_2fa_challenge_verifies_code_and_logs_in(): void
    {
        auth()->logout();

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        // Simulate the login step that stores user_id in session
        $this->session(['2fa:user_id' => $user->id, '2fa:remember' => false]);

        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->post(route('2fa.challenge'), ['code' => $validCode]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_2fa_challenge_rejects_invalid_code(): void
    {
        auth()->logout();

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->session(['2fa:user_id' => $user->id]);

        $response = $this->post(route('2fa.challenge'), ['code' => '000000']);

        $response->assertRedirect(); // Back to challenge
        $this->assertGuest();
    }

    public function test_api_login_requires_totp_when_2fa_enabled(): void
    {
        // Seed sync_cursor for API tests
        \DB::table('sync_cursor')->insert(['version' => 0]);

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        // Try without TOTP code
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)->assertJson(['requires_2fa' => true]);

        // Try with valid TOTP code
        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'totp_code' => $validCode,
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_api_login_works_without_2fa(): void
    {
        \DB::table('sync_cursor')->insert(['version' => 0]);

        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_full_2fa_setup_flow(): void
    {
        // 1. Check status — disabled
        $this->getJson('/api/2fa/status')->assertJson(['enabled' => false]);

        // 2. Setup — get secret
        $setupResponse = $this->postJson('/api/2fa/setup');
        $secret = $setupResponse->json('secret');
        $this->assertNotEmpty($secret);

        // 3. Confirm with valid TOTP
        $google2fa = new Google2FA();
        $code = $google2fa->getCurrentOtp($secret);
        $this->postJson('/api/2fa/confirm', ['code' => $code])->assertJson(['confirmed' => true]);

        // 4. Check status — enabled
        $this->getJson('/api/2fa/status')->assertJson(['enabled' => true]);

        // 5. Disable with password
        $this->postJson('/api/2fa/disable', ['password' => 'password'])->assertJson(['disabled' => true]);

        // 6. Check status — disabled again
        $this->getJson('/api/2fa/status')->assertJson(['enabled' => false]);
    }
}
