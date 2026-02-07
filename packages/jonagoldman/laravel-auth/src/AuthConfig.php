<?php

declare(strict_types=1);

namespace JonaGoldman\Auth;

final class AuthConfig
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $tokenModel
     * @param  class-string<\Illuminate\Contracts\Auth\Authenticatable>  $userModel
     * @param  list<string>  $guards
     * @param  list<string>  $statefulDomains
     */
    public function __construct(
        public readonly string $tokenModel,
        public readonly string $userModel,
        public readonly array $guards = ['session'],
        public readonly array $statefulDomains = [],
        public readonly bool $secureCookies = true,
        public readonly int $pruneDays = 30,
        public readonly int $lastUsedAtDebounce = 300,
    ) {}
}
