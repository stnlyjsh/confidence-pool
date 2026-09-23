<?php

use App\Broadcasting\PoolChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Laravel expects a plain class-name string here (it instantiates the class
// and calls its join() method itself) — the [Class::class, 'method'] array
// syntax that works for route definitions throws "unknown channel handler
// type" here instead, since join() isn't static.
Broadcast::channel('pool.{poolId}', PoolChannel::class);
