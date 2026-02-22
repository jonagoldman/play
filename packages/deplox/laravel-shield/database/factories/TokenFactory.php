<?php

declare(strict_types=1);

namespace Deplox\Shield\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Deplox\Shield\Contracts\IsAuthToken;
use Deplox\Shield\Enums\TokenType;
use Deplox\Shield\Shield;

/**
 * @extends Factory<Model>
 */
final class TokenFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Model $token): void {
            $plain = $token->getAttribute('plain');

            if ($token instanceof IsAuthToken && is_string($plain)) {
                $shield = app(Shield::class);
                $token->setPlain($shield->decorateToken($plain));
            }
        });
    }

    public function definition(): array
    {
        return [
            'type' => TokenType::Bearer,
            'token' => TokenType::Bearer->generate(),
            'expires_at' => Date::now()->addYear(),
        ];
    }
}
