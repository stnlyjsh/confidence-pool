<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Models\User;

class LedgerService
{
    /**
     * Create (or refresh the amount on) a user's buy-in entry for the
     * pool's current season. Never touches one that's already been marked
     * paid — the amount shown for a settled buy-in shouldn't shift under
     * someone after the fact just because the commissioner tweaked the
     * price for next time.
     */
    public function ensureBuyIn(Pool $pool, User $user): LedgerEntry
    {
        $entry = LedgerEntry::firstOrNew([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'season' => $pool->season_year,
            'type' => LedgerEntryType::BuyIn,
            'week' => null,
        ]);

        if ($entry->exists && $entry->is_paid) {
            return $entry;
        }

        $entry->amount_cents = $pool->buy_in_amount_cents;
        $entry->is_paid = $entry->is_paid ?? false;
        $entry->save();

        return $entry;
    }

    /**
     * Called whenever the pool's buy-in amount changes, so every active
     * participant's unpaid buy-in reflects the current price.
     */
    public function syncBuyInsForAllParticipants(Pool $pool): void
    {
        foreach ($pool->participants()->where('is_active', true)->get() as $participant) {
            $this->ensureBuyIn($pool, $participant->user);
        }
    }

    /**
     * Create weekly payout entries for the top scorer(s) that week,
     * splitting the pot evenly on a tie. Idempotent as long as nothing has
     * been marked paid yet, so the commissioner can safely re-run this
     * after a late score correction.
     *
     * @return array<int, LedgerEntry>
     */
    public function closeWeek(Pool $pool, int $week): array
    {
        return $this->createPayouts(
            $pool,
            LedgerEntryType::WeeklyPayout,
            $week,
            $pool->weekly_payout_cents,
            $this->pointsByUser($pool, $week),
        );
    }

    /**
     * Same idea as closeWeek, but for the season champion(s).
     *
     * @return array<int, LedgerEntry>
     */
    public function closeSeason(Pool $pool): array
    {
        return $this->createPayouts(
            $pool,
            LedgerEntryType::SeasonPayout,
            null,
            $pool->season_payout_cents,
            $this->pointsByUser($pool, null),
        );
    }

    /**
     * @return array<int, int> user_id => total points
     */
    private function pointsByUser(Pool $pool, ?int $week): array
    {
        return $pool->participants()
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(function (PoolParticipant $participant) use ($pool, $week) {
                $query = $participant->user->picks()
                    ->where('pool_id', $pool->id)
                    ->where('season', $pool->season_year);

                if ($week !== null) {
                    $query->where('week', $week);
                }

                return [$participant->user_id => (int) $query->sum('points_earned')];
            })
            ->all();
    }

    /**
     * @param  array<int, int>  $pointsByUser
     * @return array<int, LedgerEntry>
     */
    private function createPayouts(Pool $pool, LedgerEntryType $type, ?int $week, int $payoutCents, array $pointsByUser): array
    {
        if (empty($pointsByUser) || $payoutCents === 0) {
            return [];
        }

        // Refuse to recreate payouts once any of them have already been
        // settled — the commissioner needs to sort that out by hand rather
        // than have us silently change who was paid what.
        $existing = LedgerEntry::where('pool_id', $pool->id)
            ->where('season', $pool->season_year)
            ->where('type', $type)
            ->where('week', $week)
            ->get();

        abort_if($existing->contains('is_paid', true), 422, 'Some payouts for this period are already marked paid.');
        $existing->each->delete();

        $topScore = max($pointsByUser);
        $winnerIds = array_keys(array_filter($pointsByUser, fn ($points) => $points === $topScore));
        $shareCents = intdiv($payoutCents, count($winnerIds));

        return array_map(
            fn (int $userId) => LedgerEntry::create([
                'pool_id' => $pool->id,
                'user_id' => $userId,
                'season' => $pool->season_year,
                'type' => $type,
                'week' => $week,
                'amount_cents' => -$shareCents,
                'is_paid' => false,
            ]),
            $winnerIds,
        );
    }
}
