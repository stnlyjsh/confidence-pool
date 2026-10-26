<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a set of kickoff times into the smallest set of cron expressions
 * that cover them, so the scheduler only wakes the app during windows
 * where a game could plausibly be live — not every day, all week, forever.
 *
 * Each expression pins an exact calendar date (day-of-month + month, not a
 * recurring day-of-week), since the goal is to track the real schedule —
 * including one-off outliers like a Thanksgiving Thursday afternoon game
 * or an international Friday game — rather than a guessed recurring
 * pattern that would either miss those or poll every week needlessly.
 */
class PollingWindowGenerator
{
    /**
     * @param  Collection<int, Carbon>  $kickoffs
     * @return array<int, string> cron expressions, one per (day, hour-range) segment
     */
    public function generate(Collection $kickoffs, int $gameDurationHours = 5): array
    {
        if ($kickoffs->isEmpty()) {
            return [];
        }

        $intervals = $kickoffs
            ->map(fn (Carbon $kickoff) => [$kickoff->copy(), $kickoff->copy()->addHours($gameDurationHours)])
            ->sortBy(fn (array $interval) => $interval[0]->timestamp)
            ->values();

        $merged = [];

        foreach ($intervals as [$start, $end]) {
            $last = count($merged) - 1;

            if ($last >= 0 && $start->lte($merged[$last][1])) {
                $merged[$last][1] = $merged[$last][1]->max($end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        $expressions = [];

        foreach ($merged as [$start, $end]) {
            array_push($expressions, ...$this->splitAtDayBoundaries($start, $end));
        }

        return $expressions;
    }

    /**
     * Cron's day-of-month/hour fields only describe a single calendar day,
     * so a window crossing midnight needs one expression per day it touches.
     *
     * @return array<int, string>
     */
    private function splitAtDayBoundaries(Carbon $start, Carbon $end): array
    {
        $expressions = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $dayEnd = $cursor->copy()->endOfDay();
            $segmentEnd = $end->lt($dayEnd) ? $end : $dayEnd;

            $expressions[] = sprintf(
                '* %d-%d %d %d *',
                $cursor->hour,
                $segmentEnd->hour,
                $cursor->day,
                $cursor->month,
            );

            $cursor = $dayEnd->addSecond();
        }

        return $expressions;
    }
}
