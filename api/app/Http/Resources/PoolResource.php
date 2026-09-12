<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PoolResource extends JsonResource
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
            'name' => $this->name,
            'season_year' => $this->season_year,
            'status' => $this->status,
            'buy_in_amount_cents' => $this->buy_in_amount_cents,
            'weekly_payout_cents' => $this->weekly_payout_cents,
            'season_payout_cents' => $this->season_payout_cents,
            // Only the commissioner needs the invite code — everyone else
            // has already joined.
            'invite_code' => $this->when($request->user()?->isCommissioner(), $this->invite_code),
        ];
    }
}
