<?php

declare(strict_types=1);

use App\Http\Middleware\CacheResponse;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ReplaceCdnUrl;
use Illuminate\Auth\AuthenticationException;
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
        // Octane sits behind a local Nginx reverse proxy; trust it so request()->ip() and HTTPS detection use the forwarded client values.
        $middleware->trustProxies(at: '127.0.0.1');

        $middleware->alias([
            'cache.response' => CacheResponse::class,
        ]);

        // Force JSON response for all /api/* routes
        $middleware->api(append: [
            'throttle:api',
        ],
            prepend: [
                ForceJsonResponse::class,
                ReplaceCdnUrl::class,
            ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return responseMessage('Unauthenticated', 401);
            }
        });
    })->create();
