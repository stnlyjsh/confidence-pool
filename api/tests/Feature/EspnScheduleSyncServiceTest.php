<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Team;
use App\Services\Espn\EspnScheduleSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame('SEA', $final->homeTeam->abbreviation);
        $this->assertSame('NE', $final->awayTeam->abbreviation);

        $scheduled = Game::where('espn_event_id', '401872925')->sole();
        $this->assertSame(GameStatus::Scheduled, $scheduled->status);
        $this->assertNull($scheduled->home_score);
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
}
