<?php

namespace App\Models;

use App\Enums\GameStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['espn_event_id', 'season', 'week', 'home_team_id', 'away_team_id', 'kickoff_at', 'home_score', 'away_score', 'status', 'raw_espn_payload'])]
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
     * Whether any game is plausibly still being played right now — kicked
     * off recently but not yet in a terminal state. Used to gate tight
     * polling: NFL games rarely run past ~5 hours including overtime.
     */
    public static function hasLiveGames(): bool
    {
        return static::where('kickoff_at', '<=', now())
            ->where('kickoff_at', '>=', now()->subHours(5))
            ->whereNotIn('status', [GameStatus::Final, GameStatus::Voided, GameStatus::Canceled])
            ->exists();
    }
}
