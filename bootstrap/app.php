<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Meta memanggil endpoint ini langsung dari server-nya, tanpa sesi
        // login dan tanpa token CSRF — jadi wajib dikecualikan (§14).
        $middleware->validateCsrfTokens(except: [
            'oauth/instagram/deauthorize',
            'oauth/instagram/data-deletion',
            'oauth/meta/deauthorize',
            'oauth/meta/data-deletion',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
