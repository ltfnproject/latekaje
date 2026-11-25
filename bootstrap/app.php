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
        // Hanya percayai proxy dari env TRUSTED_PROXIES (default lokal + subnet Docker NPM)
        $rawProxies = trim((string) env('TRUSTED_PROXIES', '127.0.0.1,172.16.0.0/12'));
        if ($rawProxies === '*') {
            $middleware->trustProxies(at: '*');
        } elseif ($rawProxies !== '') {
            $proxies = array_values(array_filter(array_map('trim', explode(',', $rawProxies))));
            if ($proxies !== []) {
                $middleware->trustProxies(at: $proxies);
            }
        }

        // Pastikan akun nonaktif langsung logout + invalidasi sesi sebelum Filament Authenticate memunculkan 403 buntu
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
        );

        // Security headers global (X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // 🌐 Bahasa aplikasi mengikuti Setting app_locale (id/en)
        $middleware->append(\App\Http\Middleware\SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();