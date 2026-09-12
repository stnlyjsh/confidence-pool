<?php

namespace App\Services\Espn;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around ESPN's unofficial NFL scoreboard endpoint, isolating
 * the app from its exact (undocumented) response shape.
 */
class EspnScoreboardClient
{
    /**
     * Fetch the scoreboard for a given season/week, or ESPN's own notion of
     * "current" when both are omitted.
     *
     * @return array<string, mixed>
     */
    public function getScoreboard(?int $season = null, ?int $week = null): array
    {
        $query = array_filter([
            'seasontype' => 2,
            'year' => $season,
            'week' => $week,
        ]);

        return Http::get(config('services.espn.scoreboard_url'), $query)
            ->throw()
            ->json();
    }
}
