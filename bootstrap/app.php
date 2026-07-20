<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // Sesi habis (SESSION_LIFETIME) → token CSRF tak cocok → 419 "Page Expired".
        // Layar itu buntu: user tak tahu harus apa. Lempar ke login dengan pesan.
        // 303 (bukan 302) supaya Inertia mengikuti redirect setelah POST/PUT/DELETE —
        // exception CSRF dilempar SEBELUM middleware Inertia, jadi konversi
        // 302→303 bawaannya tak kebagian; kita set sendiri.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 419 || $request->expectsJson()) {
                return $response;
            }

            return redirect()->guest(route('login'))
                ->setStatusCode(303)
                ->with('status', 'Sesi kamu sudah habis. Silakan masuk lagi.');
        });
    })->create();
