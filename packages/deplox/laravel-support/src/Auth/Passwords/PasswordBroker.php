<?php

declare(strict_types=1);

namespace Deplox\Support\Auth\Passwords;

use Closure;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\PasswordBroker as PasswordBrokerContract;
use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;
use Illuminate\Support\Arr;
use UnexpectedValueException;

final readonly class PasswordBroker implements PasswordBrokerContract
{
    /**
     * Create a new password broker instance.
     */
    public function __construct(
        private UserProviderContract $users,
        private TokenRepositoryInterface $tokens,
    ) {}

    /**
     * Send a password reset link to a user.
     */
    public function sendResetLink(array $credentials, ?Closure $callback = null): string
    {
        // First we will check to see if we found a user at the given credentials and
        // if we did not we will redirect back to this current URI with a piece of
        // "flash" data in the session to indicate to the developers the errors.
        $user = $this->getUser($credentials);

        if (is_null($user)) {
            return self::INVALID_USER;
        }

        if ($this->tokens->recentlyCreatedToken($user)) {
            return self::RESET_THROTTLED;
        }

        $token = $this->createToken($user);

        if ($callback instanceof \Closure) {
            return $callback($user, $token) ?? self::RESET_LINK_SENT;
        }

        // Once we have the reset token, we are ready to send the message out to this
        // user with a link to reset their password. We will then redirect back to
        // the current URI having nothing set in the session to indicate errors.
        $user->sendPasswordResetNotification($token);

        return self::RESET_LINK_SENT;
    }

    /**
     * Reset the password for the given token.
     */
    public function reset(array $credentials, Closure $callback): mixed
    {
        $user = $this->getUser($credentials);

        if (is_null($user)) {
            return self::INVALID_USER;
        }

        if (! $this->tokenExists($user, $credentials['token'])) {
            return self::INVALID_TOKEN;
        }

        // Once the reset has been validated, we'll call the given callback with the
        // new password. This gives the user an opportunity to store the password
        // in their persistent storage. Then we'll delete the token and return.
        $callback($user, $credentials['password']);

        $this->deleteToken($user);

        return self::PASSWORD_RESET;
    }

    /**
     * Get the user for the given credentials.
     *
     * @throws UnexpectedValueException
     */
    public function getUser(array $credentials): ?CanResetPasswordContract
    {
        $user = $this->users->retrieveByCredentials(Arr::except($credentials, ['token']));

        throw_if($user && ! $user instanceof CanResetPasswordContract, UnexpectedValueException::class, 'User must implement CanResetPassword interface.');

        return $user;
    }

    /**
     * Create a new password reset token for the given user.
     */
    public function createToken(CanResetPasswordContract $user): string
    {
        return $this->tokens->create($user);
    }

    /**
     * Delete password reset tokens of the given user.
     */
    public function deleteToken(CanResetPasswordContract $user): void
    {
        $this->tokens->delete($user);
    }

    /**
     * Determine if the given password reset token exists and is valid.
     */
    public function tokenExists(CanResetPasswordContract $user, string $token): bool
    {
        return $this->tokens->exists($user, $token);
    }

    /**
     * Get the password reset token repository implementation.
     */
    public function getRepository(): TokenRepositoryInterface
    {
        return $this->tokens;
    }
}
