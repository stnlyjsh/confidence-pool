<?php

namespace Tests\Feature;

use App\Enums\ParticipantRole;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolTest extends TestCase
{
    use RefreshDatabase;

    private function commissioner(Pool $pool): User
    {
        $participant = PoolParticipant::factory()->commissioner()->for($pool)->create();

        return $participant->user;
    }

    private function player(Pool $pool): User
    {
        $participant = PoolParticipant::factory()->for($pool)->create();

        return $participant->user;
    }

    public function test_player_can_join_with_a_valid_invite_code(): void
    {
        $pool = Pool::factory()->create(['invite_code' => 'ABCD1234']);

        $response = $this->postJson('/api/pool/join', [
            'invite_code' => 'ABCD1234',
            'name' => 'Player Two',
            'email' => 'player2@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()->assertJsonPath('user.role', 'player');
        $this->assertDatabaseHas('pool_participants', [
            'pool_id' => $pool->id,
            'role' => ParticipantRole::Player->value,
        ]);
    }

    public function test_join_fails_with_an_invalid_invite_code(): void
    {
        Pool::factory()->create(['invite_code' => 'ABCD1234']);

        $response = $this->postJson('/api/pool/join', [
            'invite_code' => 'WRONGCODE',
            'name' => 'Player Two',
            'email' => 'player2@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_player_sees_pool_details_but_not_the_invite_code(): void
    {
        $pool = Pool::factory()->create();
        $user = $this->player($pool);

        $response = $this->actingAs($user)->getJson('/api/pool');

        $response->assertOk()
            ->assertJsonPath('data.id', $pool->id)
            ->assertJsonMissingPath('data.invite_code');
    }

    public function test_commissioner_sees_the_invite_code(): void
    {
        $pool = Pool::factory()->create();
        $user = $this->commissioner($pool);

        $response = $this->actingAs($user)->getJson('/api/pool');

        $response->assertOk()->assertJsonPath('data.invite_code', $pool->invite_code);
    }

    public function test_player_cannot_update_the_pool(): void
    {
        $pool = Pool::factory()->create();
        $user = $this->player($pool);

        $this->actingAs($user)
            ->patchJson('/api/pool', ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_commissioner_can_update_the_pool(): void
    {
        $pool = Pool::factory()->create();
        $user = $this->commissioner($pool);

        $this->actingAs($user)
            ->patchJson('/api/pool', ['buy_in_amount_cents' => 5000])
            ->assertOk()
            ->assertJsonPath('data.buy_in_amount_cents', 5000);
    }

    public function test_player_cannot_regenerate_the_invite_code(): void
    {
        $pool = Pool::factory()->create();
        $user = $this->player($pool);

        $this->actingAs($user)
            ->postJson('/api/pool/invite/regenerate')
            ->assertForbidden();
    }

    public function test_commissioner_can_regenerate_the_invite_code(): void
    {
        $pool = Pool::factory()->create(['invite_code' => 'ORIGINAL']);
        $user = $this->commissioner($pool);

        $response = $this->actingAs($user)->postJson('/api/pool/invite/regenerate');

        $response->assertOk();
        $this->assertNotSame('ORIGINAL', $response->json('data.invite_code'));
    }
}
