<?php

namespace App\Http\Controllers;

use App\Enums\ParticipantRole;
use App\Http\Controllers\Concerns\IssuesAuthTokens;
use App\Http\Requests\JoinPoolRequest;
use App\Http\Requests\UpdatePoolRequest;
use App\Http\Resources\PoolResource;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PoolController extends Controller
{
    use IssuesAuthTokens;

    /**
     * Create a player account and attach it to the pool matching the given
     * invite code.
     */
    public function join(JoinPoolRequest $request): JsonResponse
    {
        $pool = Pool::where('invite_code', $request->string('invite_code'))->firstOrFail();

        $user = DB::transaction(function () use ($request, $pool) {
            $user = User::create([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'password' => Hash::make($request->string('password')),
            ]);

            PoolParticipant::create([
                'pool_id' => $pool->id,
                'user_id' => $user->id,
                'role' => ParticipantRole::Player,
                'joined_at' => now(),
            ]);

            return $user;
        });

        return $this->tokenResponse($user, 201);
    }

    public function show(): PoolResource
    {
        return new PoolResource(Pool::sole());
    }

    public function update(UpdatePoolRequest $request): PoolResource
    {
        $pool = Pool::sole();

        $pool->update($request->validated());

        return new PoolResource($pool);
    }

    public function regenerateInvite(): PoolResource
    {
        $pool = Pool::sole();

        $pool->update(['invite_code' => Str::upper(Str::random(8))]);

        return new PoolResource($pool);
    }
}
