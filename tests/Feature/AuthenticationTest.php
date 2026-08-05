<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost:5173', '127.0.0.1:5173']]);
    }

    public function test_valid_credentials_authenticate_and_return_safe_user_data(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => 'test-secret',
            'role' => User::ROLE_OWNER,
        ]);
        $this->withSession(['login_test' => true]);
        $previousSessionId = session()->getId();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/auth/login', [
                'email' => 'owner@example.test',
                'password' => 'test-secret',
            ])->assertOk()->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => User::ROLE_OWNER,
                ],
            ])->assertJsonMissing(['password', 'remember_token']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_invalid_and_unknown_credentials_return_the_same_generic_error(): void
    {
        User::factory()->create([
            'email' => 'known@example.test',
            'password' => 'correct-password',
        ]);

        foreach ([
            ['email' => 'known@example.test', 'password' => 'wrong-password'],
            ['email' => 'unknown@example.test', 'password' => 'wrong-password'],
        ] as $credentials) {
            $this->postJson('/api/auth/login', $credentials)
                ->assertUnprocessable()
                ->assertJsonPath('message', 'The provided credentials are invalid.')
                ->assertJsonPath('errors.email.0', 'The provided credentials are invalid.');
        }
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('limited@example.test|127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'limited@example.test',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'limited@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_current_user_requires_authentication_and_returns_user(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_requires_authentication_and_ends_the_session(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();

        User::factory()->create([
            'email' => 'logout@example.test',
            'password' => 'logout-password',
        ]);
        $this->withSession(['logout_test' => true])
            ->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/auth/login', [
                'email' => 'logout@example.test',
                'password' => 'logout-password',
            ])
            ->assertOk();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Logged out successfully.']);

        Auth::forgetGuards();
        $this->assertGuest();
    }
}
