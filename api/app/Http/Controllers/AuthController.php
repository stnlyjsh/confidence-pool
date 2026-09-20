<?php

namespace App\Http\Controllers;

use App\Enums\ParticipantRole;
use App\Enums\PoolStatus;
use App\Http\Controllers\Concerns\IssuesAuthTokens;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use IssuesAuthTokens;

    /**
     * Create the commissioner's account and the (single) pool. Only
     * available before the pool exists — everyone after that joins via
     * invite code (see PoolController::join()).
     */
    public function register(RegisterRequest $request, LedgerService $ledger): JsonResponse
    {
        abort_if(Pool::query()->exists(), 422, 'The pool has already been created. Ask the commissioner for an invite link.');

        $user = DB::transaction(function () use ($request, $ledger) {
            $user = User::create([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'password' => Hash::make($request->string('password')),
            ]);

            $pool = Pool::create([
                'name' => $request->string('name')."'s Confidence Pool",
                'season_year' => now()->year,
                'commissioner_user_id' => $user->id,
                // Explicit rather than relying on the migration's DB-level
                // default — Eloquent doesn't back-fill column defaults into
                // the in-memory model after insert, so ensureBuyIn() below
                // would otherwise see null instead of 0.
                'buy_in_amount_cents' => 0,
                'weekly_payout_cents' => 0,
                'season_payout_cents' => 0,
                'invite_code' => Str::upper(Str::random(8)),
                'status' => PoolStatus::Draft,
            ]);

            PoolParticipant::create([
                'pool_id' => $pool->id,
                'user_id' => $user->id,
                'role' => ParticipantRole::Commissioner,
                'joined_at' => now(),
            ]);

            // The commissioner competes and pays in like everyone else.
            $ledger->ensureBuyIn($pool, $user);

            return $user;
        });

        return $this->tokenResponse($user, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        return $this->tokenResponse($user);
    }

    public function logout(): Response
    {
        request()->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
