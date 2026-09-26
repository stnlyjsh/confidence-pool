<?php

namespace Tests\Feature;

use App\Models\PoolParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->patchJson('/api/user', ['name' => 'New Name'])->assertUnauthorized();
    }

    public function test_a_user_can_change_their_display_name(): void
    {
        $participant = PoolParticipant::factory()->create();

        $response = $this->actingAs($participant->user)->patchJson('/api/user', ['name' => 'New Name']);

        $response->assertOk()->assertJsonPath('data.name', 'New Name');
        $this->assertSame('New Name', $participant->user->fresh()->name);
    }

    public function test_the_name_is_required(): void
    {
        $participant = PoolParticipant::factory()->create();

        $this->actingAs($participant->user)
            ->patchJson('/api/user', ['name' => ''])
            ->assertInvalid(['name']);
    }
}
