<?php

namespace App\Http\Controllers;

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
     * @return Collection<int, array{user_id: int, name: string, total_points: int, correct_count: int}>
     */
    private function standingsFor(Pool $pool, int $season, ?int $week): Collection
    {
        return $pool->participants()
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->map(function (PoolParticipant $participant) use ($season, $week) {
                $query = Pick::where('user_id', $participant->user_id)->where('season', $season);

                if ($week !== null) {
                    $query->where('week', $week);
                }

                return [
                    'user_id' => $participant->user_id,
                    'name' => $participant->user->name,
                    'total_points' => (int) (clone $query)->sum('points_earned'),
                    'correct_count' => (clone $query)->where('is_correct', true)->count(),
                ];
            })
            ->sortByDesc('total_points')
            ->values();
    }
}
