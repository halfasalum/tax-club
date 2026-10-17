<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureCanAccessAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        api: __DIR__.'/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ── Sanctum stateful domains (for web-based SPA if needed) ──
        $middleware->statefulApi();

        // ── Register the permission middleware alias ────────────────
        // Usage in routes: ->middleware('permission:add_user')
        $middleware->alias([
            'permission' => CheckPermission::class,
            'locale' => SetLocale::class,
            'admin' => EnsureCanAccessAdmin::class,
            'active' => EnsureAccountActive::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            AddSecurityHeaders::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('member.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
