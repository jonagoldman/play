<?php

declare(strict_types=1);

namespace Deplox\Shield\Actions;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Send (or re-send) the email verification notification.
 *
 * Rate-limited per user-id + IP, defaulting to 6 attempts per hour. Mirrors the
 * throttle pattern used by Login. Returns true if the notification was sent,
 * false if the user is already verified.
 */
final readonly class SendEmailVerification
{
    public function __construct(
        private RateLimiter $limiter,
        private Request $request,
        private DispatcherContract $dispatcher,
    ) {}

    /**
     * @throws ValidationException on rate limit
     */
    public function __invoke(MustVerifyEmail $user, int $maxAttempts = 6, int $decaySeconds = 3600): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $key = $this->throttleKey($user);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $this->dispatcher->dispatch(new Lockout($this->request));

            throw ValidationException::withMessages([
                'email' => [__('auth.throttle', ['seconds' => $this->limiter->availableIn($key)])],
            ])->status(429);
        }

        $this->limiter->hit($key, $decaySeconds);

        $user->sendEmailVerificationNotification();

        return true;
    }

    private function throttleKey(MustVerifyEmail $user): string
    {
        $identifier = method_exists($user, 'getKey')
            ? (string) $user->getKey()
            : $user->getEmailForVerification();

        return 'verify-email|'.$identifier.'|'.$this->request->ip();
    }
}
