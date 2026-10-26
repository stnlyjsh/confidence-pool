<?php

namespace App\Models;

use App\Enums\GameStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['espn_event_id', 'season', 'week', 'home_team_id', 'away_team_id', 'kickoff_at', 'home_score', 'away_score', 'home_team_record', 'away_team_record', 'status', 'raw_espn_payload'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'status' => GameStatus::class,
            'raw_espn_payload' => 'array',
        ];
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * The season/week the app should default to, derived from whatever's
     * already been synced rather than computed from the NFL calendar
     * (bye weeks, playoffs, and a variable season start make that fragile).
     * Prefers a game still being played, then the next upcoming one, then
     * falls back to the most recently played week if the season's over.
     *
     * @return array{season: int, week: int}|null
     */
    public static function currentWeek(): ?array
    {
        $game = static::whereNotIn('status', [GameStatus::Final, GameStatus::Voided, GameStatus::Canceled])
            ->where('kickoff_at', '<=', now())
            ->orderByDesc('kickoff_at')
            ->first()
            ?? static::where('kickoff_at', '>', now())->orderBy('kickoff_at')->first()
            ?? static::orderByDesc('kickoff_at')->first();

        return $game ? ['season' => $game->season, 'week' => $game->week] : null;
    }
}
