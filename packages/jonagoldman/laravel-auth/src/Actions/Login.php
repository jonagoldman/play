<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use JonaGoldman\Auth\Contracts\HasTokens;

final class Login
{
    /**
     * Authenticate a user by password and create a new bearer token.
     *
     * @throws ValidationException
     */
    public function __invoke(
        ?Authenticatable $user,
        string $password,
        string $field = 'email',
        ?string $tokenName = null,
    ): Model {
        if (! $user instanceof Model ||
            ! $user instanceof HasTokens ||
            ! Hash::check($password, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                $field => [__('auth.failed')],
            ]);
        }

        return $user->createToken(name: $tokenName);
    }
}
