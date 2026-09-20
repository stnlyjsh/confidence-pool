<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Pool;
use App\Models\PoolParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_player_only_sees_their_own_ledger_entries(): void
    {
        $pool = Pool::factory()->create();
        $alice = PoolParticipant::factory()->for($pool)->create();
        $bob = PoolParticipant::factory()->for($pool)->create();

        LedgerEntry::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id]);
        LedgerEntry::factory()->create(['pool_id' => $pool->id, 'user_id' => $bob->user_id]);

        $response = $this->actingAs($alice->user)->getJson('/api/ledger');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($alice->user_id, $response->json('data.0.user_id'));
    }

    public function test_a_player_cannot_see_the_all_participants_view(): void
    {
        $pool = Pool::factory()->create();
        $player = PoolParticipant::factory()->for($pool)->create();

        $this->actingAs($player->user)->getJson('/api/ledger/all')->assertForbidden();
    }

    public function test_the_commissioner_sees_every_participants_entries(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create();
        $player = PoolParticipant::factory()->for($pool)->create();

        LedgerEntry::factory()->create(['pool_id' => $pool->id, 'user_id' => $commissioner->user_id]);
        LedgerEntry::factory()->create(['pool_id' => $pool->id, 'user_id' => $player->user_id]);

        $response = $this->actingAs($commissioner->user)->getJson('/api/ledger/all');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertNotNull($response->json('data.0.user_name'));
    }

    public function test_commissioner_can_toggle_an_entry_paid_and_back(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create();
        $entry = LedgerEntry::factory()->create(['pool_id' => $pool->id, 'user_id' => $commissioner->user_id]);

        $response = $this->actingAs($commissioner->user)->postJson("/api/ledger/{$entry->id}/mark-paid");
        $response->assertOk()->assertJsonPath('data.is_paid', true);
        $this->assertNotNull($entry->fresh()->paid_at);

        $response = $this->actingAs($commissioner->user)->postJson("/api/ledger/{$entry->id}/mark-paid");
        $response->assertOk()->assertJsonPath('data.is_paid', false);
        $this->assertNull($entry->fresh()->paid_at);
    }

    public function test_a_player_cannot_mark_an_entry_paid(): void
    {
        $pool = Pool::factory()->create();
        $player = PoolParticipant::factory()->for($pool)->create();
        $entry = LedgerEntry::factory()->create(['pool_id' => $pool->id, 'user_id' => $player->user_id]);

        $this->actingAs($player->user)->postJson("/api/ledger/{$entry->id}/mark-paid")->assertForbidden();
    }
}
