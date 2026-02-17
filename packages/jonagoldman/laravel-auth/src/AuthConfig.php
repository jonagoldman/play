<?php

declare(strict_types=1);

namespace JonaGoldman\Auth;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use JonaGoldman\Auth\Concerns\IsAuthToken;
use JonaGoldman\Auth\Contracts\HasTokens;

final class AuthConfig
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $tokenModel
     * @param  class-string<Authenticatable>  $userModel
     * @param  list<string>  $guards
     * @param  list<string>  $statefulDomains
     * @param  ?int  $defaultTokenExpiration  Default token expiration in seconds (null = no default, 0 = no expiration)
     * @param  ?Closure(Authenticatable): bool  $validateUser
     */
    public function __construct(
        public readonly string $tokenModel,
        public readonly string $userModel,
        public readonly array $guards = ['session'],
        public readonly array $statefulDomains = [],
        public readonly bool $secureCookies = true,
        public readonly int $pruneDays = 30,
        public readonly int $lastUsedAtDebounce = 300,
        public readonly ?int $defaultTokenExpiration = 60 * 60 * 24 * 30,
        public readonly ?Closure $validateUser = null,
    ) {
        if (! class_exists($tokenModel)) {
            throw new InvalidArgumentException("Token model [{$tokenModel}] does not exist.");
        }

        if (! in_array(IsAuthToken::class, class_uses_recursive($tokenModel), true)) {
            throw new InvalidArgumentException("Token model [{$tokenModel}] must use the IsAuthToken trait.");
        }

        if (! class_exists($userModel)) {
            throw new InvalidArgumentException("User model [{$userModel}] does not exist.");
        }

        if (! is_subclass_of($userModel, HasTokens::class)) {
            throw new InvalidArgumentException("User model [{$userModel}] must implement the HasTokens contract.");
        }
    }
}
