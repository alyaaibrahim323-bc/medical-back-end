<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocaleFromRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',   // ✅ fix space
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // ✅ Add your custom middleware to API group (append, don't replace)
        $middleware->api(append: [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            SetLocaleFromRequest::class,
        ]);

        // ✅ alias middlewares
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
   ->withExceptions(function (Exceptions $exceptions) {

    // ✅ 401 لو مفيش auth
    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
    });

    // ✅ 403 لو role middleware منع
    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    });

    // ✅ 403 لو Authorization فشل
    $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    });

})

    ->create();
