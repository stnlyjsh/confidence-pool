<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pool_id', 'user_id', 'season', 'type', 'week', 'amount_cents', 'is_paid', 'paid_at', 'marked_paid_by_user_id', 'notes'])]
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'is_paid' => 'boolean',
            'paid_at' => 'datetime',
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

    public function markedPaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_paid_by_user_id');
    }
}
