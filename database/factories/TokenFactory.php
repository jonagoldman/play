<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Token;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\Enums\TokenType;

/**
 * @extends Factory<Token>
 */
final class TokenFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Token $token): void {
            if ($token->plain !== null) {
                $config = app(AuthConfig::class);
                $token->setPlain($config->decorateToken($token->plain));
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
