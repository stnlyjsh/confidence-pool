<?php

namespace App\Models;

use App\Enums\PoolStatus;
use Database\Factories\PoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'season_year', 'commissioner_user_id', 'buy_in_amount_cents', 'weekly_payout_cents', 'season_payout_cents', 'invite_code', 'status'])]
class Pool extends Model
{
    /** @use HasFactory<PoolFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PoolStatus::class,
        ];
    }

    public function commissioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissioner_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(PoolParticipant::class);
    }
}
