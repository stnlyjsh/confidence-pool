<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\PickController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\StandingsController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('pool/join', [PoolController::class, 'join']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', fn (Request $request) => new UserResource($request->user()->load('poolParticipant')));
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('pool', [PoolController::class, 'show']);
    Route::get('weeks/current', [GameController::class, 'currentWeek']);
    Route::get('weeks/{season}/{week}/games', [GameController::class, 'index'])
        ->whereNumber(['season', 'week']);
    Route::get('weeks/{season}/{week}/picks', [PickController::class, 'index'])
        ->whereNumber(['season', 'week']);
    Route::put('weeks/{season}/{week}/picks', [PickController::class, 'update'])
        ->whereNumber(['season', 'week']);
    Route::get('weeks/{season}/{week}/group-picks', [PickController::class, 'group'])
        ->whereNumber(['season', 'week']);

    Route::get('standings/season', [StandingsController::class, 'season']);
    Route::get('standings/weeks/{week}', [StandingsController::class, 'week'])->whereNumber('week');

    Route::get('ledger', [LedgerController::class, 'index']);

    Route::middleware('commissioner')->group(function () {
        Route::patch('pool', [PoolController::class, 'update']);
        Route::post('pool/invite/regenerate', [PoolController::class, 'regenerateInvite']);
        Route::post('admin/games/{game}/void', [AdminController::class, 'voidGame']);
        Route::patch('admin/games/{game}', [AdminController::class, 'overrideGame']);
        Route::post('admin/close-week/{week}', [AdminController::class, 'closeWeek'])->whereNumber('week');
        Route::post('admin/close-season', [AdminController::class, 'closeSeason']);
        Route::get('ledger/all', [LedgerController::class, 'all']);
        Route::post('ledger/{ledgerEntry}/mark-paid', [LedgerController::class, 'markPaid']);
    });
});
