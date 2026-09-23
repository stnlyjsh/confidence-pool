<?php

namespace Tests\Feature;

use App\Broadcasting\PoolChannel;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

    // The test environment runs with BROADCAST_CONNECTION=null (standard
    // Sail/Reverb scaffolding, so tests don't need a live broadcaster) —
    // its auth() is an unconditional no-op, so hitting /broadcasting/auth
    // here would "pass" regardless of what routes/channels.php says. This
    // tests the authorization logic directly instead, since that's the
    // part actually worth verifying.

    public function test_a_pool_member_can_join_their_pool_channel(): void
    {
        $pool = Pool::factory()->create();
        $participant = PoolParticipant::factory()->for($pool)->create();

        $this->assertTrue((new PoolChannel)->join($participant->user, $pool->id));
    }

    public function test_a_non_member_cannot_join(): void
    {
        $pool = Pool::factory()->create();
        $outsider = User::factory()->create();

        $this->assertFalse((new PoolChannel)->join($outsider, $pool->id));
    }

    public function test_a_member_of_a_different_pool_cannot_join(): void
    {
        $pool = Pool::factory()->create();
        $otherPool = Pool::factory()->create();
        $otherMember = PoolParticipant::factory()->for($otherPool)->create();

        $this->assertFalse((new PoolChannel)->join($otherMember->user, $pool->id));
    }
}
