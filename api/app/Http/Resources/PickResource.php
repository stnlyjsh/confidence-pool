<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PickResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'game_id' => $this->game_id,
            'picked_team_id' => $this->picked_team_id,
            'confidence_value' => $this->confidence_value,
            'is_auto_assigned' => $this->is_auto_assigned,
            'is_locked' => $this->game->kickoff_at->isPast(),
        ];
    }
}
