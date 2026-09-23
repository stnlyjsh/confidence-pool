<?php

use App\Http\Middleware\EnsureIsCommissioner;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // The PWA authenticates with a Sanctum bearer token, not a session cookie,
    // so /broadcasting/auth must use the stateless api guard, not the web
    // middleware group's default (session/CSRF-based) broadcasting auth.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'commissioner' => EnsureIsCommissioner::class,
        ]);

        // ApplicationBuilder::withMiddleware() always registers a default
        // redirectGuestsTo(route('login')) before this callback runs. This
        // app has no such route (pure JSON API), so an unauthenticated
        // request that doesn't explicitly send Accept: application/json
        // (e.g. a plain curl, or a browser hitting the URL directly) would
        // hit RouteNotFoundException instead of a clean 401 — every client
        // we actually built (the PWA, our tests) always sends that header,
        // which is exactly why this stayed hidden until a bare curl request
        // against the deployed app surfaced it.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
