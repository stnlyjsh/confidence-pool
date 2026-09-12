<?php

namespace App\Models;

use App\Enums\ParticipantRole;
use Database\Factories\PoolParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pool_id', 'user_id', 'role', 'is_active', 'joined_at'])]
class PoolParticipant extends Model
{
    /** @use HasFactory<PoolParticipantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
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
}
