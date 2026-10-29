<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureInternalUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => CheckRole::class,
            'api.token' => AuthenticateApiToken::class,
            'internal' => EnsureInternalUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Seluruh error di /api/* dikembalikan sebagai JSON (tanpa stack trace di production).
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
    })->create();
