<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Token;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use JonaGoldman\Auth\Enums\TokenType;

/**
 * @extends Factory<Token>
 */
final class TokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => TokenType::BEARER,
            'token' => TokenType::BEARER->generate(),
            'expires_at' => Date::now()->addYear(),
        ];
    }
}
