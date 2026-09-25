<?php

namespace App\Http\Controllers;

use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameController extends Controller
{
    public function index(int $season, int $week): AnonymousResourceCollection
    {
        $games = Game::with(['homeTeam', 'awayTeam'])
            ->where('season', $season)
            ->where('week', $week)
            ->orderBy('kickoff_at')
            ->get();

        return GameResource::collection($games);
    }

    public function currentWeek(): JsonResponse
    {
        $current = Game::currentWeek();

        abort_if($current === null, 404, 'No games synced yet.');

        return response()->json(['data' => $current]);
    }
}
