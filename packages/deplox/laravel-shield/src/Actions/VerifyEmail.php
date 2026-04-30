<?php

declare(strict_types=1);

namespace Deplox\Shield\Actions;

use Deplox\Shield\Shield;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Application;

/**
 * Verify a user's email via a signed verification URL.
 *
 * Validates that the verification hash matches sha1 of the user's email
 * (mirrors Laravel's default VerifyEmail notification). On success, marks
 * the user verified and dispatches the Verified event.
 */
final readonly class VerifyEmail
{
    public function __construct(
        private Shield $shield,
        private DispatcherContract $dispatcher,
        private Application $app,
    ) {}

    /**
     * @return bool true if the user was newly verified, false if already verified
     */
    public function __invoke(string|int $userId, string $hash): bool
    {
        /** @var class-string<Authenticatable> $userModel */
        $userModel = $this->shield->userModel;

        /** @var (Authenticatable&MustVerifyEmail)|null $user */
        $user = $userModel::query()->find($userId);

        if (! $user instanceof MustVerifyEmail) {
            return false;
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return false;
        }

        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->markEmailAsVerified();

        $this->dispatcher->dispatch(new Verified($user));

        return true;
    }
}
