<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TokenType;
use App\Models\Token;
use App\Models\User;
use DateTimeInterface;

final class TokenService
{
    public function createToken(User $user, ?DateTimeInterface $expiresAt = null): Token
    {
        return $user->tokens()->create([
            'type' => TokenType::BEARER,
            'token' => TokenType::BEARER->random(),
            'expires_at' => $expiresAt,
        ]);
    }

    public function findToken(string $token): ?Token
    {
        $query = Token::query();

        if (mb_strpos($token, '|') === false) {
            return $query->where('token', hash('sha256', $token))->first();
        }

        [$id, $token] = explode('|', $token, 2);

        /**
         * Comparing using `hash_equals` ensures the comparison always takes exactly the same amount of time
         * regardless of the inputted string, preventing an attacker from figuring out the secret key.
         */
        if (($instance = $query->find($id)) && hash_equals($instance->token, hash('sha256', $token))) {
            return $instance;
        }

        return null;
    }
}
