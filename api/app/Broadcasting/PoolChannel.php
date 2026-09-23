<?php

namespace App\Broadcasting;

use App\Models\User;

class PoolChannel
{
    /**
     * Only members of that specific pool can listen for its live score and
     * standings updates.
     */
    public function join(User $user, int $poolId): bool
    {
        return $user->poolParticipant?->pool_id === $poolId;
    }
}
