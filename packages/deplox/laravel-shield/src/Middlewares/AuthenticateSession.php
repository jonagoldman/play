<?php

declare(strict_types=1);

namespace Deplox\Shield\Middlewares;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Deplox\Shield\Shield;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateSession
{
    /**
     * @param  \Illuminate\Auth\AuthManager  $auth
     */
    public function __construct(
        private AuthFactory $auth,
        private Shield $shield,
    ) {}

    /**
     * Handle incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->user()) {
            return $next($request);
        }

        $guards = Collection::make($this->shield->guards)
            ->mapWithKeys(fn ($guard) => [$guard => $this->auth->guard($guard)])
            ->filter(fn ($guard) => $guard instanceof SessionGuard);

        $shouldLogout = $guards->filter(
            fn ($guard, $driver) => $request->session()->has('password_hash_'.$driver)
        )->filter(
            fn ($guard, $driver) => ! $this->validatePasswordHash(
                $guard,
                $request->user()->getAuthPassword(),
                $request->session()->get('password_hash_'.$driver)
            )
        );

        if ($shouldLogout->isNotEmpty()) {
            $shouldLogout->each->logoutCurrentDevice();

            $request->session()->flush();

            throw new AuthenticationException('Unauthenticated.', [...$shouldLogout->keys()->all(), 'dynamic']);
        }

        return tap($next($request), function () use ($request, $guards) {
            if (! is_null($guard = $this->getFirstGuardWithUser($guards->keys()))) {
                $this->storePasswordHashInSession($request, $guard);
            }
        });
    }

    /**
     * Get the first authentication guard that has a user.
     */
    private function getFirstGuardWithUser(Collection $guards): ?string
    {
        return $guards->first(function ($guard) {
            $guardInstance = $this->auth->guard($guard);

            return method_exists($guardInstance, 'hasUser') && $guardInstance->hasUser();
        });
    }

    /**
     * Store the user's current password hash in the session.
     */
    private function storePasswordHashInSession(Request $request, string $guard): void
    {
        /** @var SessionGuard|\Illuminate\Contracts\Auth\Guard */
        $guardInstance = $this->auth->guard($guard);

        $request->session()->put([
            "password_hash_{$guard}" => method_exists($guardInstance, 'hashPasswordForCookie')
                ? $guardInstance->hashPasswordForCookie($guardInstance->user()->getAuthPassword())
                : $guardInstance->user()->getAuthPassword(),
        ]);
    }

    /**
     * Validate the password hash against the stored value.
     */
    private function validatePasswordHash(SessionGuard $guard, ?string $passwordHash, string $storedValue): bool
    {
        // Try new HMAC format first (Laravel 12.45.0+)...
        if (method_exists($guard, 'hashPasswordForCookie')) {
            if (hash_equals($guard->hashPasswordForCookie($passwordHash), $storedValue)) {
                return true;
            }
        }

        // Fall back to raw password hash format for backward compatibility...
        return hash_equals($passwordHash ?? '', $storedValue);
    }
}
