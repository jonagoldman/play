<?php

declare(strict_types=1);

namespace JonaGoldman\Auth;

use Illuminate\Database\Eloquent\Model;
use JonaGoldman\Auth\Models\Token;

final class AuthService
{
    private static string $tokenModel = Token::class;

    public static function useTokenModel(string $class): void
    {
        self::$tokenModel = $class;
    }

    public static function tokenModel(): string
    {
        return self::$tokenModel;
    }

    public static function newToken(): Model
    {
        return new (self::$tokenModel);
    }
}
