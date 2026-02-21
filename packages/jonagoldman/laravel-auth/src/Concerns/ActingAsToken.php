<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Concerns;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use JonaGoldman\Auth\Contracts\HasTokens as HasTokensContract;
use JonaGoldman\Auth\Enums\TokenType;

trait ActingAsToken
{
    public function actingAsToken(
        Authenticatable&HasTokensContract $user,
        TokenType $type = TokenType::Bearer,
        ?DateTimeInterface $expiresAt = null,
    ): static {
        $token = $user->createToken($type, $expiresAt);

        return $this->withToken($token->plain, 'Bearer');
    }
}
