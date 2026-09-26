<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
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
            'season' => $this->season,
            'week' => $this->week,
            'kickoff_at' => $this->kickoff_at,
            'is_locked' => $this->kickoff_at->isPast(),
            'status' => $this->status,
            'home_team' => new TeamResource($this->whenLoaded('homeTeam')),
            'away_team' => new TeamResource($this->whenLoaded('awayTeam')),
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'home_team_record' => $this->home_team_record,
            'away_team_record' => $this->away_team_record,
        ];
    }
}
