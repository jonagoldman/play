<?php

declare(strict_types=1);

namespace Deplox\Shield\Middlewares;

use Closure;
use Deplox\Shield\Shield;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow incoming requests from the SPA to authenticate using session cookies,
 * while still allowing requests from third parties to authenticate using API tokens.
 */
final class StatefulFrontend
{
    public function __construct(
        private Shield $shield,
    ) {}

    /**
     * Determine if the given request is from the first-party application frontend.
     */
    public function fromFrontend(Request $request): bool
    {
        $domain = $request->headers->get('referer') ?: $request->headers->get('origin');

        if (is_null($domain)) {
            return false;
        }

        $domain = Str::replaceFirst('https://', '', $domain);
        $domain = Str::replaceFirst('http://', '', $domain);
        $domain = Str::endsWith($domain, '/') ? $domain : "{$domain}/";

        $stateful = array_filter($this->shield->statefulDomains());
        $withSubdomains = $this->shield->statefulSubdomains();

        return Str::is(Collection::make($stateful)->flatMap(function ($uri) use ($withSubdomains) {
            $trimmed = mb_trim($uri);
            $patterns = [$trimmed.'/*'];

            if ($withSubdomains) {
                $patterns[] = '*.'.$trimmed.'/*';
            }

            return $patterns;
        })->all(), $domain);
    }

    /**
     * Handle incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return (new Pipeline(app()))->send($request)->through(
            $this->fromFrontend($request) ? $this->frontendMiddleware() : []
        )->then(function ($request) use ($next) {
            return $next($request);
        });
    }

    /**
     * Get the middleware that should be applied to requests from the first-party SPA (frontend).
     */
    private function frontendMiddleware(): array
    {
        return array_values(array_filter(array_unique([
            'encrypt_cookies' => $this->shield->middlewares['encrypt_cookies'],
            'response_cookies' => \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            'start_session' => \Illuminate\Session\Middleware\StartSession::class,
            'validate_csrf_token' => $this->shield->middlewares['validate_csrf_token'],
            'authenticate_session' => $this->shield->middlewares['authenticate_session'],
        ])));
    }
}
