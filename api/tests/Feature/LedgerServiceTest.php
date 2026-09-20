<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_buy_in_creates_an_entry_matching_the_pool_amount(): void
    {
        $pool = Pool::factory()->create(['buy_in_amount_cents' => 5000]);
        $participant = PoolParticipant::factory()->for($pool)->create();

        app(LedgerService::class)->ensureBuyIn($pool, $participant->user);

        $this->assertDatabaseHas('ledger_entries', [
            'pool_id' => $pool->id,
            'user_id' => $participant->user_id,
            'type' => LedgerEntryType::BuyIn->value,
            'amount_cents' => 5000,
            'is_paid' => false,
        ]);
    }

    public function test_ensure_buy_in_is_idempotent(): void
    {
        $pool = Pool::factory()->create(['buy_in_amount_cents' => 5000]);
        $participant = PoolParticipant::factory()->for($pool)->create();

        $ledger = app(LedgerService::class);
        $ledger->ensureBuyIn($pool, $participant->user);
        $ledger->ensureBuyIn($pool, $participant->user);

        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_sync_buy_ins_updates_the_amount_for_unpaid_entries(): void
    {
        $pool = Pool::factory()->create(['buy_in_amount_cents' => 5000]);
        $participant = PoolParticipant::factory()->for($pool)->create();
        app(LedgerService::class)->ensureBuyIn($pool, $participant->user);

        $pool->update(['buy_in_amount_cents' => 7500]);
        app(LedgerService::class)->syncBuyInsForAllParticipants($pool);

        $this->assertDatabaseHas('ledger_entries', [
            'pool_id' => $pool->id,
            'user_id' => $participant->user_id,
            'amount_cents' => 7500,
        ]);
    }

    public function test_sync_buy_ins_never_changes_an_already_paid_entry(): void
    {
        $pool = Pool::factory()->create(['buy_in_amount_cents' => 5000]);
        $participant = PoolParticipant::factory()->for($pool)->create();
        $ledger = app(LedgerService::class);
        $entry = $ledger->ensureBuyIn($pool, $participant->user);
        $entry->update(['is_paid' => true]);

        $pool->update(['buy_in_amount_cents' => 9999]);
        $ledger->syncBuyInsForAllParticipants($pool);

        $this->assertSame(5000, $entry->fresh()->amount_cents);
    }

    public function test_close_week_pays_the_top_scorer(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026, 'weekly_payout_cents' => 10000]);
        $winner = PoolParticipant::factory()->for($pool)->create();
        $loser = PoolParticipant::factory()->for($pool)->create();

        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $winner->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 20]);
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $loser->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 5]);

        $entries = app(LedgerService::class)->closeWeek($pool, 1);

        $this->assertCount(1, $entries);
        $this->assertSame($winner->user_id, $entries[0]->user_id);
        $this->assertSame(-10000, $entries[0]->amount_cents);
        $this->assertSame(LedgerEntryType::WeeklyPayout, $entries[0]->type);
    }

    public function test_close_week_splits_the_payout_evenly_on_a_tie(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026, 'weekly_payout_cents' => 10000]);
        $a = PoolParticipant::factory()->for($pool)->create();
        $b = PoolParticipant::factory()->for($pool)->create();

        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $a->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 15]);
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $b->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 15]);

        $entries = app(LedgerService::class)->closeWeek($pool, 1);

        $this->assertCount(2, $entries);
        $this->assertSame(-5000, $entries[0]->amount_cents);
        $this->assertSame(-5000, $entries[1]->amount_cents);
    }

    public function test_close_week_is_idempotent_when_nothing_is_paid_yet(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026, 'weekly_payout_cents' => 10000]);
        $winner = PoolParticipant::factory()->for($pool)->create();
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $winner->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 20]);

        $ledger = app(LedgerService::class);
        $ledger->closeWeek($pool, 1);
        $ledger->closeWeek($pool, 1);

        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_close_week_refuses_to_recreate_a_settled_payout(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026, 'weekly_payout_cents' => 10000]);
        $winner = PoolParticipant::factory()->for($pool)->create();
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $winner->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 20]);

        $ledger = app(LedgerService::class);
        $entries = $ledger->closeWeek($pool, 1);
        $entries[0]->update(['is_paid' => true]);

        $this->expectException(HttpException::class);
        $ledger->closeWeek($pool, 1);
    }

    public function test_close_season_pays_the_season_champion(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026, 'season_payout_cents' => 20000]);
        $champion = PoolParticipant::factory()->for($pool)->create();
        $other = PoolParticipant::factory()->for($pool)->create();

        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $champion->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 100]);
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $other->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 50]);

        $entries = app(LedgerService::class)->closeSeason($pool);

        $this->assertCount(1, $entries);
        $this->assertSame($champion->user_id, $entries[0]->user_id);
        $this->assertSame(LedgerEntryType::SeasonPayout, $entries[0]->type);
        $this->assertNull($entries[0]->week);
    }
}
