<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Concerns;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use JonaGoldman\Auth\Contracts\OwnsTokens;
use JonaGoldman\Auth\Enums\TokenType;

trait ActingAsToken
{
    public function actingAsToken(
        Authenticatable&OwnsTokens $user,
        TokenType $type = TokenType::Bearer,
        ?DateTimeInterface $expiresAt = null,
    ): static {
        $token = $user->createToken($type, $expiresAt);

        return $this->withToken($token->plain, 'Bearer');
    }
}
