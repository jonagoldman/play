<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use JonaGoldman\Auth\Contracts\IsAuthToken as IsAuthTokenContract;
use JonaGoldman\Auth\Enums\TokenType;
use JonaGoldman\Auth\Shield;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTokens
{
    public function token(): HasOne
    {
        return $this->tokens()->one()->latestOfMany();
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(app(Shield::class)->tokenModel);
    }

    public function createToken(TokenType $type = TokenType::Bearer, ?DateTimeInterface $expiresAt = null, ?string $name = null): Model&IsAuthTokenContract
    {
        $shield = app(Shield::class);

        $expiresAt ??= $shield->defaultTokenExpiration !== null
            ? now()->addSeconds($shield->defaultTokenExpiration)
            : null;

        $random = $type->generate();

        $token = $this->tokens()->create([
            'name' => $name,
            'type' => $type,
            'token' => $random,
            'expires_at' => $expiresAt,
        ]);

        $token->setPlain($shield->decorateToken($random));

        return $token;
    }
}
