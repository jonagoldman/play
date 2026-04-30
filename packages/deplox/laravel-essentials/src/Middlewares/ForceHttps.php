<?php

declare(strict_types=1);

namespace Deplox\Essentials\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect HTTP requests to HTTPS in production.
 *
 * Returns a 301 to the HTTPS-equivalent URL when the incoming request is
 * insecure. No-op in non-production environments and for already-secure
 * requests. Heavier than HttpPrinciple's URL::forceHttps (which only
 * affects URL generation): this physically redirects the client.
 */
final class ForceHttps
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction() || $request->isSecure()) {
            return $next($request);
        }

        return new RedirectResponse('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
    }
}
