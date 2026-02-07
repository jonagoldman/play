<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Token;
use App\Models\User;
use DateTimeInterface;
use JonaGoldman\Auth\Enums\TokenType;

final class TokenService
{
    public function createToken(User $user, ?DateTimeInterface $expiresAt = null): Token
    {
        return $user->tokens()->create([
            'type' => TokenType::BEARER,
            'token' => TokenType::BEARER->generate(),
            'expires_at' => $expiresAt,
        ]);
    }
}
