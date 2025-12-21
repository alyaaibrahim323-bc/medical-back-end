<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocaleFromRequest;



return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
        $middleware->appendToGroup('api', SetLocaleFromRequest::class);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware) {
    $middleware->api([
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (
        \Illuminate\Auth\AuthenticationException $e,
        $request
    ) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }
    });
})->create();
