<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TokenType;
use App\Models\Token;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Token>
 */
final class TokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            // 'user_id' => User::factory(),
            'type' => TokenType::BEARER,
            'token' => TokenType::BEARER->random(),
            'expires_at' => Date::now()->addYear(),
        ];
    }
}
