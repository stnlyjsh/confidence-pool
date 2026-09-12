<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait IssuesAuthTokens
{
    private function tokenResponse(User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user->load('poolParticipant')),
        ], $status);
    }
}
