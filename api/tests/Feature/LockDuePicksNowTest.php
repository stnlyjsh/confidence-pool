<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockDuePicksNowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_locks_due_games_across_multiple_weeks_at_once(): void
    {
        $pool = Pool::factory()->create();
        $participant = PoolParticipant::factory()->for($pool)->create();

        Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHour()]);
        Game::factory()->create(['season' => 2026, 'week' => 2, 'kickoff_at' => now()->subMinutes(30)]);
        // Not due yet — shouldn't be touched.
        Game::factory()->create(['season' => 2026, 'week' => 2, 'kickoff_at' => now()->addHour()]);

        $this->artisan('picks:lock-due-now')->assertSuccessful();

        $this->assertSame(2, Pick::where('user_id', $participant->user_id)->count());
    }

    public function test_it_ignores_kickoffs_older_than_the_recent_window(): void
    {
        $pool = Pool::factory()->create();
        PoolParticipant::factory()->for($pool)->create();

        Game::factory()->create(['season' => 2025, 'week' => 18, 'kickoff_at' => now()->subDays(30)]);

        $this->artisan('picks:lock-due-now')->assertSuccessful();

        $this->assertDatabaseCount('picks', 0);
    }
}
