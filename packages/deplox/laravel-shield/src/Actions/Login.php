<?php

declare(strict_types=1);

namespace Deplox\Shield\Actions;

use Deplox\Shield\Shield;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use function mb_strtolower;
use function transliterator_transliterate;

final class Login
{
    public function __construct(
        private AuthFactory $auth,
        private Shield $shield,
        private RateLimiter $limiter,
        private Request $request,
    ) {}

    /**
     * @param  array{email?: string, password?: string}  $credentials
     *
     * @throws ValidationException
     */
    public function __invoke(array $credentials, bool $stateful = false, string $field = 'email'): Authenticatable
    {
        $throttleKey = $this->throttleKey($credentials[$field] ?? '');

        $this->ensureNotRateLimited($throttleKey, $field);

        $guard = $this->guard();

        $success = $stateful
            ? $guard->attemptWhen($credentials, [$this->shield->validateUser])
            : $this->validate($guard, $credentials);

        if (! $success) {
            $this->limiter->hit($throttleKey, $this->shield->loginDecaySeconds);

            throw ValidationException::withMessages([
                $field => [__('auth.failed')],
            ]);
        }

        $this->limiter->clear($throttleKey);

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

    /**
     * @throws ValidationException
     */
    private function ensureNotRateLimited(string $key, string $field): void
    {
        if (! $this->limiter->tooManyAttempts($key, $this->shield->maxLoginAttempts)) {
            return;
        }

        event(new Lockout($this->request));

        $seconds = $this->limiter->availableIn($key);

        throw ValidationException::withMessages([
            $field => [__('auth.throttle', ['seconds' => $seconds])],
        ])->status(429);
    }

    private function throttleKey(string $identifier): string
    {
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $identifier);

        return mb_strtolower($transliterated ?: $identifier).'|'.$this->request->ip();
    }
}
