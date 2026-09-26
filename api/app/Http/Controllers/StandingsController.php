<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class StandingsController extends Controller
{
    public function season(): JsonResponse
    {
        $pool = Pool::sole();

        return response()->json(['data' => $this->standingsFor($pool, $pool->season_year, null)]);
    }

    public function week(int $week): JsonResponse
    {
        $pool = Pool::sole();

        return response()->json(['data' => $this->standingsFor($pool, $pool->season_year, $week)]);
    }

    /**
     * @return Collection<int, array{user_id: int, name: string, total_points: int, correct_count: int, pct_correct: float|null, max_potential_points: int}>
     */
    private function standingsFor(Pool $pool, int $season, ?int $week): Collection
    {
        return $pool->participants()
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->map(function (PoolParticipant $participant) use ($season, $week) {
                $query = Pick::where('user_id', $participant->user_id)
                    ->where('season', $season)
                    ->with('game:id,status');

                if ($week !== null) {
                    $query->where('week', $week);
                }

                $totalPoints = 0;
                $correctCount = 0;
                $decidedCount = 0;
                $maxPotentialPoints = 0;

                foreach ($query->get() as $pick) {
                    if ($pick->is_correct !== null) {
                        $decidedCount++;
                        $totalPoints += $pick->points_earned;
                        $maxPotentialPoints += $pick->points_earned;

                        if ($pick->is_correct) {
                            $correctCount++;
                        }

                        continue;
                    }

                    // Still in play: if a team's been picked and the game
                    // hasn't been voided, that confidence value is still
                    // reachable, so it counts toward the ceiling.
                    if ($pick->picked_team_id !== null && $pick->game->status !== GameStatus::Voided) {
                        $maxPotentialPoints += $pick->confidence_value;
                    }
                }

                return [
                    'user_id' => $participant->user_id,
                    'name' => $participant->user->name,
                    'total_points' => $totalPoints,
                    'correct_count' => $correctCount,
                    'pct_correct' => $decidedCount > 0 ? round($correctCount / $decidedCount * 100, 1) : null,
                    'max_potential_points' => $maxPotentialPoints,
                ];
            })
            ->sortByDesc('total_points')
            ->values();
    }
}
