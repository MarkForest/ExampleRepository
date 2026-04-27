<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-ID');

        if (! is_string($correlationId) || $correlationId === '') {
            $correlationId = (string) Str::uuid();
        }

        Log::shareContext([
            'correlation_id' => $correlationId,
        ]);

        $request->headers->set('X-Correlation-ID', $correlationId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
