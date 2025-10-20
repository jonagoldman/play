<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class UseRequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-REQUEST-ID') ?? mb_strtolower((string) Str::ulid());

        Context::add('requestId', $requestId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-REQUEST-ID', $requestId, true);

        return $response;
    }
}
