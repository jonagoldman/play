<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Contracts;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use JonaGoldman\Auth\Enums\TokenType;

interface OwnsTokens
{
    public function createToken(TokenType $type = TokenType::Bearer, ?DateTimeInterface $expiresAt = null, ?string $name = null): Model&IsAuthToken;
}
