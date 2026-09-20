<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'season' => $this->season,
            'week' => $this->week,
            'type' => $this->type,
            'amount_cents' => $this->amount_cents,
            'is_paid' => $this->is_paid,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
        ];
    }
}
