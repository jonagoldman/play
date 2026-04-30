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
 * Emits at INFO level. Pair with a structured log channel to feed observability
 * tooling. The IP and user-agent are deliberately omitted — they belong in
 * access logs (nginx, ALB), not application logs.
 */
final class LogRequests
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
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
