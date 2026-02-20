<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Contracts;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use JonaGoldman\Auth\Enums\TokenType;

interface HasTokens
{
    public function token(): HasOne;

    public function tokens(): HasMany;

    public function createToken(TokenType $type = TokenType::Bearer, ?DateTimeInterface $expiresAt = null, ?string $name = null): Model&IsAuthToken;
}
