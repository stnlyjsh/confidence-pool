<?php

namespace App\Models;

use Database\Factories\PickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pool_id', 'user_id', 'game_id', 'picked_team_id', 'confidence_value', 'season', 'week', 'is_auto_assigned', 'is_correct', 'points_earned'])]
class Pick extends Model
{
    /** @use HasFactory<PickFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_auto_assigned' => 'boolean',
            'is_correct' => 'boolean',
        ];
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function pickedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'picked_team_id');
    }
}
