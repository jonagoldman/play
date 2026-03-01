<?php

declare(strict_types=1);

namespace Deplox\Shield\Middlewares;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class DenyAuthenticated
{
    /**
     * @param  \Illuminate\Auth\AuthManager  $auth
     */
    public function __construct(private AuthFactory $auth) {}

    /**
     * Handle incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws HttpException
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                throw new HttpException(403, 'Already authenticated.');
            }
        }

        return $next($request);
    }
}
