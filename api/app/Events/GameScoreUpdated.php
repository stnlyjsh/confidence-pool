<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Broadcast now, not queued: score changes are low-frequency enough that a
// dedicated queue worker process isn't worth the operational overhead here.
class GameScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $poolId,
        public int $gameId,
        public ?int $homeScore,
        public ?int $awayScore,
        public string $status,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("pool.{$this->poolId}")];
    }

    public function broadcastAs(): string
    {
        return 'GameScoreUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->gameId,
            'home_score' => $this->homeScore,
            'away_score' => $this->awayScore,
            'status' => $this->status,
        ];
    }
}
