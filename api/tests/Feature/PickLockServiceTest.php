<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Services\PickLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickLockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_auto_fills_a_missing_pick_with_the_highest_unused_value(): void
    {
        $pool = Pool::factory()->create();
        $participant = PoolParticipant::factory()->for($pool)->create();

        // 4 games this week: 3 already locked (2 already picked, 1 missed),
        // 1 still open (shouldn't be touched).
        $pickedGameA = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHours(3)]);
        $pickedGameB = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHours(2)]);
        $missedGame = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHour()]);
        $openGame = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);

        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $participant->user_id, 'game_id' => $pickedGameA->id,
            'confidence_value' => 4, 'season' => 2026, 'week' => 1,
        ]);
        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $participant->user_id, 'game_id' => $pickedGameB->id,
            'confidence_value' => 1, 'season' => 2026, 'week' => 1,
        ]);

        app(PickLockService::class)->lockDueGames(2026, 1);

        $autoFilled = Pick::where('user_id', $participant->user_id)->where('game_id', $missedGame->id)->sole();
        // Values 1 and 4 are taken; highest of the remaining {2, 3} is 3.
        $this->assertSame(3, $autoFilled->confidence_value);
        $this->assertTrue($autoFilled->is_auto_assigned);
        $this->assertNull($autoFilled->picked_team_id);

        $this->assertDatabaseMissing('picks', ['game_id' => $openGame->id, 'user_id' => $participant->user_id]);
    }

    public function test_it_processes_multiple_missed_games_in_kickoff_order(): void
    {
        $pool = Pool::factory()->create();
        $participant = PoolParticipant::factory()->for($pool)->create();

        $alreadyPicked = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHours(3)]);
        $firstMissed = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHours(2)]);
        $secondMissed = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHour()]);

        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $participant->user_id, 'game_id' => $alreadyPicked->id,
            'confidence_value' => 1, 'season' => 2026, 'week' => 1,
        ]);

        app(PickLockService::class)->lockDueGames(2026, 1);

        $first = Pick::where('user_id', $participant->user_id)->where('game_id', $firstMissed->id)->sole();
        $second = Pick::where('user_id', $participant->user_id)->where('game_id', $secondMissed->id)->sole();

        // Earlier kickoff is processed first and claims the higher value.
        $this->assertSame(3, $first->confidence_value);
        $this->assertSame(2, $second->confidence_value);
    }

    public function test_it_ignores_inactive_participants(): void
    {
        $pool = Pool::factory()->create();
        $inactive = PoolParticipant::factory()->for($pool)->create(['is_active' => false]);

        Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHour()]);

        app(PickLockService::class)->lockDueGames(2026, 1);

        $this->assertDatabaseMissing('picks', ['user_id' => $inactive->user_id]);
    }

    public function test_it_is_idempotent(): void
    {
        $pool = Pool::factory()->create();
        PoolParticipant::factory()->for($pool)->create();

        Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHour()]);

        $service = app(PickLockService::class);
        $service->lockDueGames(2026, 1);
        $service->lockDueGames(2026, 1);

        $this->assertDatabaseCount('picks', 1);
    }

    public function test_it_does_not_touch_games_that_have_not_kicked_off(): void
    {
        $pool = Pool::factory()->create();
        PoolParticipant::factory()->for($pool)->create();

        Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addHour()]);

        $count = app(PickLockService::class)->lockDueGames(2026, 1);

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('picks', 0);
    }
}
