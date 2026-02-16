<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Token;
use App\Models\User;
use DateTimeInterface;
use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\Enums\TokenType;

final class TokenService
{
    public function __construct(
        private AuthConfig $config,
    ) {}

    public function createToken(
        User $user,
        ?DateTimeInterface $expiresAt = null,
        ?string $name = null,
    ): Token {
        $expiresAt ??= $this->config->defaultTokenExpiration !== null
            ? now()->addSeconds($this->config->defaultTokenExpiration)
            : null;

        return $user->tokens()->create([
            'name' => $name,
            'type' => TokenType::Bearer,
            'token' => TokenType::Bearer->generate(),
            'expires_at' => $expiresAt,
        ]);
    }
}
