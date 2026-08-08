<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The admin SPA runs on its own origin; Sanctum treats the request as
     * stateful — and therefore session-backed — only when that origin is
     * configured. Tests send it so they exercise the real path.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost:3001');
    }

    public function test_a_user_can_log_in_and_read_their_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@taida.vn',
            'password' => 'secret-password',
            'role' => UserRole::Admin,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@taida.vn',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@taida.vn');
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'admin@taida.vn', 'password' => 'secret-password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@taida.vn',
            'password' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'admin@taida.vn', 'password' => 'secret-password']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'admin@taida.vn',
                'password' => 'wrong',
            ])->assertUnprocessable();
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@taida.vn',
            'password' => 'secret-password',
        ])->assertUnprocessable();

        $this->assertStringContainsString('seconds', $response->json('errors.email.0'));
        $this->assertGuest();

        RateLimiter::clear('login:admin@taida.vn|127.0.0.1');
    }

    public function test_logging_out_ends_the_session(): void
    {
        User::factory()->create(['email' => 'admin@taida.vn', 'password' => 'secret-password']);

        // Logging in for real rather than via actingAs(), so the session the
        // logout has to tear down actually exists.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@taida.vn',
            'password' => 'secret-password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/logout')->assertOk();

        // The `auth:sanctum` middleware leaves `sanctum` as the default guard
        // holding the user it already resolved. A browser would send a fresh
        // request against the invalidated session; forgetting the guards is
        // how the test reproduces that.
        $this->assertGuest('web');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_request_from_an_unconfigured_origin_gets_a_clear_error(): void
    {
        User::factory()->create(['email' => 'admin@taida.vn', 'password' => 'secret-password']);

        $response = $this->withHeader('Origin', 'http://not-configured.test')
            ->postJson('/api/v1/auth/login', [
                'email' => 'admin@taida.vn',
                'password' => 'secret-password',
            ])
            ->assertStatus(421);

        $this->assertStringContainsString('SANCTUM_STATEFUL_DOMAINS', $response->json('message'));
    }

    public function test_admin_endpoints_reject_guests(): void
    {
        $this->getJson('/api/v1/admin/services')->assertUnauthorized();
        $this->postJson('/api/v1/admin/posts')->assertUnauthorized();
    }
}
