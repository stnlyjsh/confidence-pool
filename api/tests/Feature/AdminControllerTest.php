<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_commissioner_can_void_a_game(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        $game = Game::factory()->final()->create();
        $pick = Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $game->home_team_id, 'confidence_value' => 10]);

        $response = $this->actingAs($commissioner)->postJson("/api/admin/games/{$game->id}/void");

        $response->assertOk()->assertJsonPath('data.status', 'voided');
        $this->assertSame(GameStatus::Voided, $game->fresh()->status);
        $pick->refresh();
        $this->assertNull($pick->is_correct);
        $this->assertNull($pick->points_earned);
    }

    public function test_player_cannot_void_a_game(): void
    {
        $pool = Pool::factory()->create();
        $player = PoolParticipant::factory()->for($pool)->create()->user;
        $game = Game::factory()->create();

        $this->actingAs($player)->postJson("/api/admin/games/{$game->id}/void")->assertForbidden();
    }
}
