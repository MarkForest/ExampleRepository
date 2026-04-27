<?php

use App\Exceptions\Api\V1\InsufficientFundsException;
use App\Http\Middleware\AssignCorrelationId;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AssignCorrelationId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'message' => 'Validation error Custom',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }
            return response()->json([
                'message' => 'Record not found Custom',
            ], 404);
        });

        $exceptions->render(function (InsufficientFundsException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        });
    })->create();
