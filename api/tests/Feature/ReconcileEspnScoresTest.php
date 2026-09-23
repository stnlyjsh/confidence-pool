<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Services\Espn\EspnScheduleSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ReconcileEspnScoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resyncs_each_distinct_recent_week_exactly_once(): void
    {
        Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subDay()]);
        Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHours(2)]);
        Game::factory()->create(['season' => 2026, 'week' => 2, 'kickoff_at' => now()->subHours(5)]);
        // Outside the reconciliation window — shouldn't trigger a sync.
        Game::factory()->create(['season' => 2025, 'week' => 18, 'kickoff_at' => now()->subDays(30)]);

        $this->mock(EspnScheduleSyncService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sync')->once()->with(2026, 1);
            $mock->shouldReceive('sync')->once()->with(2026, 2);
        });

        $this->artisan('espn:reconcile')->assertSuccessful();
    }
}
