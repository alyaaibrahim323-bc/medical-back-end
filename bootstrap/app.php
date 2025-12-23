<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocaleFromRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // ✅ خلي الـ API middleware stack طبيعي (Laravel default + bindings)
        // IMPORTANT: لا تحطي EnsureFrontendRequestsAreStateful إلا لو SPA cookies
        // وإنتِ عندك Mobile + Bearer Token => مش محتاجاه
        $middleware->api([
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // ✅ locale middleware على api
        $middleware->appendToGroup('api', SetLocaleFromRequest::class);

        // ✅ alias للميدلوير
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ✅ 401 دايمًا لأي API request حتى لو Accept مش application/json
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
        });

        // ✅ 403 للـ role / authorization
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($request->is('api/*') && $e->getStatusCode() === 403) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        });
    })
    ->create();
