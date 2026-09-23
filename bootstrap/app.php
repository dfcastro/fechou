<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        /*
         * O Asaas envia POST diretamente para este endpoint
         * e não possui uma sessão / token CSRF do navegador.
         *
         * A autenticação do webhook é feita no controller
         * pelo header "asaas-access-token".
         */
        $middleware->preventRequestForgery(
            except: [
                'webhooks/asaas',
            ]
        );

        $middleware->alias([
            'admin' =>
                \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) =>
                $request->is('api/*')
                || $request->expectsJson(),
        );
    })
    ->create();
