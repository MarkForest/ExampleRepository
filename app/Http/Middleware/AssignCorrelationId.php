<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-ID');

        if (! is_string($correlationId) || $correlationId === '') {
            $correlationId = (string) Str::uuid();
        }

        Context::add('correlation_id', $correlationId);
        Context::add('endpoint', sprintf('%s %s', $request->method(), $request->path()));
        Context::add('request_method', $request->method());
        Context::add('request_path', $request->path());
        Context::add('user_id', $request->user()?->getAuthIdentifier());

        Log::shareContext(Context::all());

        \Sentry\configureScope(static function (Scope $scope) use ($correlationId, $request): void {
            $scope->setTag('correlation_id', $correlationId);
            $scope->setExtra('correlation_id', $correlationId);
            $scope->setExtra('endpoint', sprintf('%s %s', $request->method(), $request->path()));
        });

        $request->headers->set('X-Correlation-ID', $correlationId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
