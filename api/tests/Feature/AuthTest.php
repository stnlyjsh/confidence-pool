<?php

namespace Tests\Feature;

use App\Enums\ParticipantRole;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_the_commissioner_and_the_pool(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Stan',
            'email' => 'stan@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()->assertJsonPath('user.role', 'commissioner');

        $this->assertDatabaseHas('users', ['email' => 'stan@example.com']);
        $this->assertDatabaseCount('pools', 1);
        $this->assertSame(
            ParticipantRole::Commissioner,
            PoolParticipant::sole()->role,
        );
    }

    public function test_register_is_blocked_once_a_pool_already_exists(): void
    {
        Pool::factory()->create();

        $response = $this->postJson('/api/register', [
            'name' => 'Someone Else',
            'email' => 'else@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'else@example.com']);
    }

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api');

        $this->withToken($token->plainTextToken)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
