<?php

declare(strict_types=1);

namespace Deplox\Essentials\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attach a Content-Security-Policy header to outgoing responses.
 *
 * Pulls policy directives from `config('essentials.csp')` (an array of
 * directive => list-of-sources). When the config is empty or missing,
 * the middleware is a no-op so apps can adopt CSP incrementally.
 *
 * Example config:
 *   'csp' => [
 *     'default-src' => ["'self'"],
 *     'script-src' => ["'self'", "'unsafe-inline'"],
 *   ]
 */
final class ContentSecurityPolicy
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        /** @var array<string, list<string>>|null $directives */
        $directives = config('essentials.csp');

        if (! is_array($directives) || $directives === []) {
            return $response;
        }

        $policy = collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');

        $response->headers->set('Content-Security-Policy', $policy);

        return $response;
    }
}
