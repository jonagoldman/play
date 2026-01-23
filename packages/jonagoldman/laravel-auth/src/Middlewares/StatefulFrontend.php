<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Middlewares;

use Closure;
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
    /**
     * A placeholder to instruct to include the current request host in the list of stateful domains.
     */
    public static string $currentRequestHostPlaceholder = '__CURRENT_REQUEST_HOST__';

    /**
     * Determine if the given request is from the first-party application frontend.
     */
    public static function fromFrontend(Request $request): bool
    {
        $domain = $request->headers->get('referer') ?: $request->headers->get('origin');

        if (is_null($domain)) {
            return false;
        }

        $domain = Str::replaceFirst('https://', '', $domain);
        $domain = Str::replaceFirst('http://', '', $domain);
        $domain = Str::endsWith($domain, '/') ? $domain : "{$domain}/";

        $stateful = array_filter(self::frontendDomains());

        return Str::is(Collection::make($stateful)->map(function ($uri) use ($request) {
            $uri = $uri === static::$currentRequestHostPlaceholder ? $request->getHttpHost() : $uri;

            return mb_trim($uri).'/*';
        })->all(), $domain);
    }

    /**
     * Requests from the following domains / hosts will receive stateful API authentication cookies.
     * Typically, these should include your local and production domains which access your API via a frontend SPA.
     */
    public static function frontendDomains(): array
    {
        $appUrl = config('app.url');

        // the current application URL from the "APP_URL" environment variable - with port.
        $appUrlWithPort = $appUrl ? ','.parse_url($appUrl, PHP_URL_HOST).(parse_url($appUrl, PHP_URL_PORT) ? ':'.parse_url($appUrl, PHP_URL_PORT) : '') : '';

        $values = [
            'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
            ','.self::$currentRequestHostPlaceholder,
            $appUrlWithPort,
            config('app.frontend_url') ? ','.parse_url(config('app.frontend_url'), PHP_URL_HOST) : '',
        ];

        $formatted = config('app.stateful_domains') ?? sprintf(str_repeat('%s', count($values)), ...$values);

        return explode(',', $formatted);
    }

    /**
     * Handle incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->configureSecureCookieSessions();

        return (new Pipeline(app()))->send($request)->through(
            self::fromFrontend($request) ? $this->frontendMiddleware() : []
        )->then(function ($request) use ($next) {
            return $next($request);
        });
    }

    /**
     * Configure secure cookie sessions.
     */
    private function configureSecureCookieSessions(): void
    {
        config([
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);
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

        array_unshift($middleware, function (Request $request, Closure $next) {
            $request->attributes->set('dynamic', true);

            return $next($request);
        });

        return $middleware;
    }
}
