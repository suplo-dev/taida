<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
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
        // In production PHP-FPM only ever sees nginx. Without this the request
        // looks like plain HTTP, so the session cookie loses its Secure flag
        // and every generated URL comes back as http://.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES') === '*'
                ? '*'
                : array_filter(explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))),
        );

        // Cookie-based Sanctum auth for the Nuxt admin SPA.
        $middleware->statefulApi();

        // No login page exists, so guests must never be redirected to one.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->api(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // There is no login page to redirect to — this application only speaks JSON.
        $exceptions->render(fn (AuthenticationException $e) => response()->json(
            ['message' => $e->getMessage()],
            Response::HTTP_UNAUTHORIZED,
        ));
    })->create();
