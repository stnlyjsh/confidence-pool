<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Events\GameScoreUpdated;
use App\Events\StandingsUpdated;
use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\Team;
use App\Services\Espn\EspnScheduleSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EspnScheduleSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEspnResponse(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/espn_scoreboard_week1.json'), true);

        Http::fake([
            'site.api.espn.com/*' => Http::response($fixture),
        ]);
    }

    public function test_sync_creates_teams_and_games_from_the_fixture(): void
    {
        $this->fakeEspnResponse();

        $count = app(EspnScheduleSyncService::class)->sync(2026, 1);

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('teams', 4);
        $this->assertDatabaseCount('games', 2);

        $final = Game::where('espn_event_id', '401872656')->sole();
        $this->assertSame(GameStatus::Final, $final->status);
        $this->assertSame(13, $final->home_score);
        $this->assertSame(10, $final->away_score);
        $this->assertSame('1-0', $final->home_team_record);
        $this->assertSame('0-1', $final->away_team_record);
        $this->assertSame('SEA', $final->homeTeam->abbreviation);
        $this->assertSame('NE', $final->awayTeam->abbreviation);
        $this->assertSame('Seattle', $final->homeTeam->city);
        $this->assertSame('New England', $final->awayTeam->city);

        $scheduled = Game::where('espn_event_id', '401872925')->sole();
        $this->assertSame(GameStatus::Scheduled, $scheduled->status);
        $this->assertNull($scheduled->home_score);
        // Unlike the score, the record is meaningful (and available) before
        // kickoff too, so it's captured regardless of game status.
        $this->assertSame('0-0', $scheduled->home_team_record);
    }

    public function test_sync_is_idempotent_on_re_run(): void
    {
        $this->fakeEspnResponse();
        $sync = app(EspnScheduleSyncService::class);

        $sync->sync(2026, 1);
        $sync->sync(2026, 1);

        $this->assertDatabaseCount('teams', 4);
        $this->assertDatabaseCount('games', 2);
    }

    public function test_sync_updates_an_existing_game_when_its_status_changes(): void
    {
        $original = json_decode(file_get_contents(__DIR__.'/../Fixtures/espn_scoreboard_week1.json'), true);

        // Same fixture, but the second game (previously scheduled) is now
        // final, simulating ESPN reporting a completed game on a later poll.
        $updated = $original;
        $updated['events'][1]['competitions'][0]['status']['type']['completed'] = true;
        $updated['events'][1]['competitions'][0]['status']['type']['name'] = 'STATUS_FINAL';
        $updated['events'][1]['competitions'][0]['competitors'][0]['score'] = '20';
        $updated['events'][1]['competitions'][0]['competitors'][1]['score'] = '14';

        // A plain second Http::fake() call would not take effect here: Laravel
        // resolves overlapping wildcard fakes in registration order, so the
        // first-registered stub would keep matching. A sequence is the
        // correct way to return different responses across calls.
        Http::fake([
            'site.api.espn.com/*' => Http::sequence()->push($original)->push($updated),
        ]);

        $sync = app(EspnScheduleSyncService::class);

        $sync->sync(2026, 1);
        $this->assertSame(GameStatus::Scheduled, Game::where('espn_event_id', '401872925')->sole()->status);

        $sync->sync(2026, 1);

        $updated = Game::where('espn_event_id', '401872925')->sole();
        $this->assertSame(GameStatus::Final, $updated->status);
        $this->assertSame(20, $updated->home_score);
        $this->assertSame(14, $updated->away_score);
        $this->assertDatabaseCount('games', 2);
    }

    public function test_sync_reuses_existing_teams_across_multiple_weeks(): void
    {
        Team::factory()->create(['espn_team_id' => '26', 'abbreviation' => 'SEA']);

        $this->fakeEspnResponse();
        app(EspnScheduleSyncService::class)->sync(2026, 1);

        $this->assertDatabaseCount('teams', 4);
    }

    public function test_sync_scores_picks_for_a_game_that_syncs_in_as_final(): void
    {
        $this->fakeEspnResponse();

        // Pre-create the teams with the same espn_team_id the fixture uses
        // (26 = SEA, 17 = NE) so the sync's team upsert updates these exact
        // rows in place rather than creating new ones with different ids —
        // otherwise the game's home_team_id would change out from under
        // the pick's picked_team_id when the sync runs.
        $homeTeam = Team::factory()->create(['espn_team_id' => '26']);
        $awayTeam = Team::factory()->create(['espn_team_id' => '17']);

        // Pre-create the game (unfinished) with a pick, matching the fixture's
        // final game, so the sync's upsert transitions it to final in place.
        $game = Game::factory()->create([
            'espn_event_id' => '401872656',
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);
        $pick = Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $homeTeam->id, 'confidence_value' => 9]);

        app(EspnScheduleSyncService::class)->sync(2026, 1);

        $pick->refresh();
        // Fixture has the home team (SEA) winning 13-10.
        $this->assertTrue($pick->is_correct);
        $this->assertSame(9, $pick->points_earned);
    }

    public function test_sync_broadcasts_a_game_score_update_when_a_game_changes(): void
    {
        $pool = Pool::factory()->create();
        Event::fake([GameScoreUpdated::class]);
        $this->fakeEspnResponse();

        app(EspnScheduleSyncService::class)->sync(2026, 1);

        $final = Game::where('espn_event_id', '401872656')->sole();
        Event::assertDispatched(GameScoreUpdated::class, fn (GameScoreUpdated $event) => $event->poolId === $pool->id
            && $event->gameId === $final->id
            && $event->status === 'final'
            && $event->homeScore === 13);
    }

    public function test_sync_does_not_rebroadcast_an_unchanged_game(): void
    {
        Pool::factory()->create();
        $this->fakeEspnResponse();
        app(EspnScheduleSyncService::class)->sync(2026, 1);

        Event::fake([GameScoreUpdated::class]);
        app(EspnScheduleSyncService::class)->sync(2026, 1);

        Event::assertNotDispatched(GameScoreUpdated::class);
    }

    public function test_sync_broadcasts_standings_updated_when_it_scores_a_pick(): void
    {
        $pool = Pool::factory()->create();
        Event::fake([StandingsUpdated::class]);
        $this->fakeEspnResponse();

        $homeTeam = Team::factory()->create(['espn_team_id' => '26']);
        $awayTeam = Team::factory()->create(['espn_team_id' => '17']);
        $game = Game::factory()->create([
            'espn_event_id' => '401872656',
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);
        Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $homeTeam->id, 'confidence_value' => 9]);

        app(EspnScheduleSyncService::class)->sync(2026, 1);

        Event::assertDispatched(StandingsUpdated::class, fn (StandingsUpdated $event) => $event->poolId === $pool->id && $event->week === 1);
    }

    public function test_sync_does_not_broadcast_before_a_pool_exists(): void
    {
        Event::fake([GameScoreUpdated::class, StandingsUpdated::class]);
        $this->fakeEspnResponse();

        app(EspnScheduleSyncService::class)->sync(2026, 1);

        Event::assertNotDispatched(GameScoreUpdated::class);
        Event::assertNotDispatched(StandingsUpdated::class);
    }

    public function test_sync_completes_even_if_broadcasting_is_unreachable(): void
    {
        Pool::factory()->create();
        $this->fakeEspnResponse();

        // Real dispatch (not Event::fake()) so this exercises the actual
        // try/catch in GameUpdateBroadcaster, standing in for Reverb being
        // down mid-sync.
        Event::listen(GameScoreUpdated::class, fn () => throw new \RuntimeException('Reverb is down'));

        $count = app(EspnScheduleSyncService::class)->sync(2026, 1);

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('games', 2);
    }
}
