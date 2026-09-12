<?php

namespace App\Services\Espn;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Carbon;

class EspnScheduleSyncService
{
    public function __construct(private readonly EspnScoreboardClient $client) {}

    /**
     * Sync the given season/week's schedule and scores, or whatever ESPN
     * reports as the current week when both are omitted.
     */
    public function sync(?int $season = null, ?int $week = null): int
    {
        $payload = $this->client->getScoreboard($season, $week);

        $events = $payload['events'] ?? [];

        foreach ($events as $event) {
            $this->syncEvent($event);
        }

        return count($events);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function syncEvent(array $event): void
    {
        $competition = $event['competitions'][0];
        $competitors = collect($competition['competitors']);

        $home = $competitors->firstWhere('homeAway', 'home');
        $away = $competitors->firstWhere('homeAway', 'away');

        $homeTeam = $this->upsertTeam($home['team']);
        $awayTeam = $this->upsertTeam($away['team']);

        $status = $this->mapStatus($competition['status']['type']);

        // ESPN reports "0" for both scores before kickoff rather than
        // omitting them, so only trust the score once the game has actually
        // started — otherwise a scheduled 0-0 game is indistinguishable
        // from a played one.
        $hasScore = in_array($status, [GameStatus::InProgress, GameStatus::Final], true);

        Game::updateOrCreate(
            ['espn_event_id' => $event['id']],
            [
                'season' => $event['season']['year'],
                'week' => $event['week']['number'],
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'kickoff_at' => Carbon::parse($event['date']),
                'home_score' => $hasScore ? $this->scoreOf($home) : null,
                'away_score' => $hasScore ? $this->scoreOf($away) : null,
                'status' => $status,
                'raw_espn_payload' => $event,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $team
     */
    private function upsertTeam(array $team): Team
    {
        return Team::updateOrCreate(
            ['espn_team_id' => $team['id']],
            [
                'abbreviation' => $team['abbreviation'],
                'name' => $team['displayName'],
                'logo_url' => $team['logo'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $competitor
     */
    private function scoreOf(array $competitor): ?int
    {
        return is_numeric($competitor['score'] ?? null) ? (int) $competitor['score'] : null;
    }

    /**
     * @param  array<string, mixed>  $statusType
     */
    private function mapStatus(array $statusType): GameStatus
    {
        $name = $statusType['name'] ?? '';

        return match (true) {
            (bool) ($statusType['completed'] ?? false) => GameStatus::Final,
            str_contains($name, 'POSTPONED') => GameStatus::Postponed,
            str_contains($name, 'CANCEL') => GameStatus::Canceled,
            ($statusType['state'] ?? 'pre') === 'in' => GameStatus::InProgress,
            default => GameStatus::Scheduled,
        };
    }
}
