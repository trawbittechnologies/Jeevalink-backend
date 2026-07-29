<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Standalone health route — no middleware, no APP_KEY needed
            // Railway healthcheck hits this to verify the container is alive
            Route::get('/health', function () {
                return response()->json([
                    'status'    => 'healthy',
                    'service'   => 'Jeevalink API',
                    'timestamp' => now()->toISOString(),
                ], 200);
            })->name('health.check');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ── CORS must be first — handles OPTIONS preflight before anything else ──
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // Trust all proxies — required for Railway's reverse proxy
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuthMiddleware::class,
            'jwt.role' => \App\Http\Middleware\JwtRoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

