<?php

use App\Exceptions\EscrowException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is a JSON-only API: every error uses {"error": {"code", "message", ...}}.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->render(fn (EscrowException $e) => response()->json([
            'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()] + ($e->context ? ['details' => $e->context] : []),
        ], $e->status));

        $exceptions->render(fn (ValidationException $e) => response()->json([
            'error' => ['code' => 'validation_failed', 'message' => $e->getMessage(), 'fields' => $e->errors()],
        ], 422));

        // Model-binding misses arrive here as NotFoundHttpException wrapping ModelNotFoundException.
        $exceptions->render(function (NotFoundHttpException $e) {
            $prev = $e->getPrevious();
            $message = $prev instanceof Illuminate\Database\Eloquent\ModelNotFoundException
                ? class_basename($prev->getModel()).' not found.'
                : 'Route not found.';

            return response()->json(['error' => ['code' => 'not_found', 'message' => $message]], 404);
        });

        $exceptions->render(fn (HttpExceptionInterface $e) => response()->json([
            'error' => ['code' => 'http_error', 'message' => $e->getMessage() ?: 'HTTP error'],
        ], $e->getStatusCode()));
    })->create();
