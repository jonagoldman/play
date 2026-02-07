<?php

declare(strict_types=1);

namespace JonaGoldman\Auth;

use Illuminate\Database\Eloquent\Model;
use JonaGoldman\Auth\Models\Token;

use function explode;
use function hash;
use function hash_equals;
use function mb_strpos;

final class AuthService
{
    private static string $tokenModel = Token::class;

    private static ?string $userModel = null;

    public static function useTokenModel(string $class): void
    {
        self::$tokenModel = $class;
    }

    public static function tokenModel(): string
    {
        return self::$tokenModel;
    }

    public static function useUserModel(string $class): void
    {
        self::$userModel = $class;
    }

    public static function userModel(): string
    {
        return self::$userModel ?? config('auth.providers.users.model');
    }

    public static function newToken(): Model
    {
        return new (self::$tokenModel);
    }

    public static function findToken(string $token): ?Model
    {
        $query = self::$tokenModel::query();

        if (mb_strpos($token, '|') === false) {
            return $query->where('token', hash('sha256', $token))->first();
        }

        [$id, $secret] = explode('|', $token, 2);

        $instance = $query->find($id);

        if ($instance && hash_equals($instance->token, hash('sha256', $secret))) {
            return $instance;
        }

        return null;
    }
}
