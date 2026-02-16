<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JonaGoldman\Auth\AuthConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow incoming requests from the SPA to authenticate using session cookies,
 * while still allowing requests from third parties to authenticate using API tokens.
 */
final class StatefulFrontend
{
    public function __construct(
        private AuthConfig $config,
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

        $stateful = array_filter($this->config->statefulDomains);

        return Str::is(Collection::make($stateful)->map(function ($uri) {
            return mb_trim($uri).'/*';
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
        $middleware = array_values(array_filter(array_unique([
            'encrypt_cookies' => \Illuminate\Cookie\Middleware\EncryptCookies::class,
            'response_cookies' => \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            'start_session' => \Illuminate\Session\Middleware\StartSession::class,
            'verify_csrf_token' => \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'authenticate_session' => AuthenticateSession::class,
        ])));

        return $middleware;
    }
}
