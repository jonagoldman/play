<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\Enums\TokenType;

/**
 * @extends Factory<Model>
 */
final class TokenFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Model $token): void {
            $plain = $token->getAttribute('plain');

            if (method_exists($token, 'setPlain') && is_string($plain)) {
                $config = app(AuthConfig::class);
                $token->setPlain($config->decorateToken($plain));
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
