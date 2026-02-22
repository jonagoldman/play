<?php

declare(strict_types=1);

namespace Deplox\Shield\Actions;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Validation\ValidationException;
use Deplox\Shield\Shield;

final class Login
{
    public function __construct(
        private AuthFactory $auth,
        private Shield $shield,
    ) {}

    /**
     * @param  array{email?: string, password?: string}  $credentials
     *
     * @throws ValidationException
     */
    public function __invoke(array $credentials, bool $stateful = false, string $field = 'email'): Authenticatable
    {
        $guard = $this->guard();

        $success = $stateful
            ? $guard->attemptWhen($credentials, [$this->shield->validateUser])
            : $this->validate($guard, $credentials);

        if (! $success) {
            throw ValidationException::withMessages([
                $field => [__('auth.failed')],
            ]);
        }

        return $guard->user();
    }

    /**
     * Validate credentials without session side effects (for API/token flow).
     */
    private function validate(SessionGuard $guard, array $credentials): bool
    {
        if (! $guard->once($credentials)) {
            return false;
        }

        if (! ($this->shield->validateUser)($guard->user())) {
            return false;
        }

        return true;
    }

    private function guard(): SessionGuard
    {
        $name = $this->shield->guards[0] ?? 'session';

        /** @var SessionGuard */
        return $this->auth->guard($name);
    }
}
