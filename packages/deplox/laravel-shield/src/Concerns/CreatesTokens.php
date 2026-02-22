<?php

declare(strict_types=1);

namespace Deplox\Shield\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Deplox\Shield\Contracts\IsAuthToken as IsAuthTokenContract;
use Deplox\Shield\Enums\TokenType;
use Deplox\Shield\Shield;

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
