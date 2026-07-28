<?php

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
    ->withMiddleware(function (Middleware $middleware): void {
        // Lets routes/api.php authenticate via the shared session cookie
        // (Sanctum SPA mode) instead of bearer tokens.
        $middleware->statefulApi();

        // This app has no web-based "login" route to redirect guests to —
        // every route is API-only. Without this, unauthenticated requests
        // that don't send Accept: application/json 500 instead of 401,
        // because the default middleware tries to redirect to a route
        // named "login" that doesn't exist.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
