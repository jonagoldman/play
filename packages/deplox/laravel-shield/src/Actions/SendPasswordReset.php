<?php

declare(strict_types=1);

namespace Deplox\Shield\Actions;

use Closure;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;

/**
 * Initiate a password reset by emailing a reset link.
 *
 * Thin delegate to Laravel's password broker. Returns the broker's status string
 * (Password::RESET_LINK_SENT, INVALID_USER, RESET_THROTTLED, ...). Pair with
 * RevokeTokensOnPasswordReset (Phase 3.5) to wipe tokens once the user completes
 * the reset.
 */
final readonly class SendPasswordReset
{
    public function __construct(
        private PasswordBrokerFactory $brokers,
        private ValidatorFactory $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(array $input, ?Closure $onCreated = null, ?string $broker = null): string
    {
        $credentials = $this->validator->validate($input, [
            'email' => ['required', 'email'],
        ]);

        return $this->brokers->broker($broker)->sendResetLink($credentials, $onCreated);
    }
}
