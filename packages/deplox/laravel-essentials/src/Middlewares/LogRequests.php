<?php

declare(strict_types=1);

namespace Deplox\Essentials\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Structured per-request log entry: method, path, status, duration in ms.
 *
 * Emits at INFO level when `essentials.log_requests` is truthy in config.
 * Disabled by default so apps can opt in selectively. IP and user-agent are
 * omitted — those belong in access logs (nginx, ALB), not application logs.
 */
final class LogRequests
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('essentials.log_requests', false)) {
            return $next($request);
        }

        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        Log::info('request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);

        return $response;
    }
}
