<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePicksRequest;
use App\Http\Resources\PickResource;
use App\Models\Pick;
use App\Models\Pool;
use App\Services\PickLockService;
use App\Services\PickValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PickController extends Controller
{
    public function __construct(
        private readonly PickLockService $lockService,
        private readonly PickValidationService $validationService,
    ) {}

    public function index(Request $request, int $season, int $week): AnonymousResourceCollection
    {
        // Catch up any game that kicked off since this user last loaded the
        // page, so what they see already reflects any auto-filled picks.
        $this->lockService->lockDueGames($season, $week);

        $picks = Pick::where('pool_id', Pool::sole()->id)
            ->where('user_id', $request->user()->id)
            ->where('season', $season)
            ->where('week', $week)
            ->with('game')
            ->get();

        return PickResource::collection($picks);
    }

    public function update(UpdatePicksRequest $request, int $season, int $week): AnonymousResourceCollection
    {
        $picks = $this->validationService->replaceUnlockedPicks(
            $request->user(),
            Pool::sole(),
            $season,
            $week,
            $request->validated('picks'),
        );

        return PickResource::collection($picks);
    }
}
