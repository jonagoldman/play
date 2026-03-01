<?php

declare(strict_types=1);

namespace Deplox\Shield\Middlewares;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveCurrentUser
{
    /**
     * Handle incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, string $parameter = 'user'): Response
    {
        $route = $request->route();

        if ($route?->parameter($parameter) === 'me') {
            $user = $request->user();

            if (! $user) {
                throw new AuthenticationException('Unauthenticated.');
            }

            $route->setParameter($parameter, $user);
        }

        return $next($request);
    }
}
