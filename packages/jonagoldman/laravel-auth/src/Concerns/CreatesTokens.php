<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use JonaGoldman\Auth\Contracts\IsAuthToken as IsAuthTokenContract;
use JonaGoldman\Auth\Enums\TokenType;
use JonaGoldman\Auth\Shield;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait CreatesTokens
{
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
