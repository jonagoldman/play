<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Token;
use App\Models\User;
use DateTimeInterface;

final class TokenService
{
    public function createToken(
        User $user,
        ?DateTimeInterface $expiresAt = null,
        ?string $name = null,
    ): Token {
        /** @var Token */
        return $user->createToken(expiresAt: $expiresAt, name: $name);
    }
}
