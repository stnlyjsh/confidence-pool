<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PoolController;
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

    Route::middleware('commissioner')->group(function () {
        Route::patch('pool', [PoolController::class, 'update']);
        Route::post('pool/invite/regenerate', [PoolController::class, 'regenerateInvite']);
    });
});
