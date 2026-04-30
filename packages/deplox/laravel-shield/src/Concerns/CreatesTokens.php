<?php

declare(strict_types=1);

namespace Deplox\Shield\Concerns;

use DateTimeInterface;
use Deplox\Shield\Contracts\IsAuthToken as IsAuthTokenContract;
use Deplox\Shield\Enums\TokenLimitBehavior;
use Deplox\Shield\Enums\TokenType;
use Deplox\Shield\Exceptions\TokenLimitExceededException;
use Deplox\Shield\Shield;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait CreatesTokens
{
    public function createToken(TokenType $type = TokenType::Bearer, ?DateTimeInterface $expiresAt = null, ?string $name = null): Model&IsAuthTokenContract
    {
        $shield = app(Shield::class);

        $this->enforceTokenLimit($shield);

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

    /**
     * Enforce the per-user token cap configured on Shield.
     *
     * - When maxTokensPerUser is null, no enforcement.
     * - When at or above the limit and onTokenLimit is Reject, throw.
     * - When at or above the limit and onTokenLimit is PruneOldest,
     *   delete the oldest tokens until count + 1 <= limit.
     */
    private function enforceTokenLimit(Shield $shield): void
    {
        if ($shield->maxTokensPerUser === null) {
            return;
        }

        $current = $this->tokens()->count();

        if ($current < $shield->maxTokensPerUser) {
            return;
        }

        if ($shield->onTokenLimit === TokenLimitBehavior::Reject) {
            throw TokenLimitExceededException::forUser($shield->maxTokensPerUser);
        }

        $excess = $current - $shield->maxTokensPerUser + 1;
        $oldestIds = $this->tokens()
            ->orderBy('created_at')
            ->limit($excess)
            ->pluck($this->tokens()->getRelated()->getKeyName());

        if ($oldestIds->isNotEmpty()) {
            $this->tokens()->whereIn($this->tokens()->getRelated()->getKeyName(), $oldestIds)->delete();
        }
    }
}
