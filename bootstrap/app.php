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
        // Inertia: bagikan shared props (auth, flash) ke tiap request web
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // `|| expectsJson()` = perilaku bawaan Laravel yang tadinya ketimpa.
        // Tanpa ini, endpoint fetch() (drag kartu, tandai selesai) membalas 302 HTML
        // saat validasi/otorisasi gagal — fetch mengikuti redirect & resolve 200,
        // sehingga kegagalan lolos diam-diam. Inertia tak terpengaruh: request-nya
        // ber-Accept text/html, jadi expectsJson() tetap false & tetap dapat redirect.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
