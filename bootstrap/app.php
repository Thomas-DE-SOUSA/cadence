<?php

use Cadence\Shared\Domain\DomainException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Typed business errors carry an ErrorCode; the edge maps it to a status.
        $exceptions->render(function (DomainException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = str_contains($e->code(), 'NOT_FOUND')
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return new JsonResponse(['code' => $e->code(), 'message' => $e->getMessage()], $status);
        });
    })->create();
