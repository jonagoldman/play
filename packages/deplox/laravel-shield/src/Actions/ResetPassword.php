<?php

declare(strict_types=1);

namespace Deplox\Shield\Actions;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Str;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;

/**
 * Complete a password reset using a previously delivered token.
 *
 * Returns the broker's status string. On success, dispatches Laravel's PasswordReset
 * event so listeners can react (the package's RevokeTokensOnPasswordReset wipes tokens
 * when revokeOnPasswordChange is enabled).
 */
final readonly class ResetPassword
{
    public function __construct(
        private PasswordBrokerFactory $brokers,
        private HasherContract $hasher,
        private DispatcherContract $dispatcher,
        private ValidatorFactory $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(array $input, ?string $broker = null): string
    {
        $credentials = $this->validator->validate($input, [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'token' => ['required', 'string'],
        ]);

        return $this->brokers->broker($broker)->reset(
            $credentials,
            function (CanResetPassword $user, string $password): void {
                $user->forceFill([
                    'password' => $this->hasher->make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $this->dispatcher->dispatch(new PasswordReset($user));
            },
        );
    }
}
