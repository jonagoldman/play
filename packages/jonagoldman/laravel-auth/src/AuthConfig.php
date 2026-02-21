<?php

declare(strict_types=1);

namespace JonaGoldman\Auth;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use JonaGoldman\Auth\Contracts\HasTokens;
use JonaGoldman\Auth\Contracts\IsAuthToken;

use function hash;
use function mb_strlen;
use function mb_substr;

final class AuthConfig
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $tokenModel
     * @param  class-string<Authenticatable>  $userModel
     * @param  list<string>  $guards
     * @param  list<string>  $statefulDomains
     * @param  ?int  $defaultTokenExpiration  Default token expiration in seconds (null = no default, 0 = no expiration)
     * @param  ?Closure(Authenticatable): bool  $validateUser
     * @param  array<string, class-string|null>  $middlewares
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
        public readonly string $tokenPrefix = '',
        public readonly string $csrfCookiePath = '/auth/csrf-cookie',
        public readonly array $middlewares = [
            'encrypt_cookies' => \Illuminate\Cookie\Middleware\EncryptCookies::class,
            'validate_csrf_token' => \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'authenticate_session' => Middlewares\AuthenticateSession::class,
        ],
    ) {
        if (! is_subclass_of($tokenModel, IsAuthToken::class)) {
            throw new InvalidArgumentException("Token model [{$tokenModel}] must implement the IsAuthToken contract.");
        }

        if (! class_exists($userModel)) {
            throw new InvalidArgumentException("User model [{$userModel}] does not exist.");
        }

        if (! is_subclass_of($userModel, HasTokens::class)) {
            throw new InvalidArgumentException("User model [{$userModel}] must implement the HasTokens contract.");
        }
    }

    /**
     * Decorate a raw random token with the configured prefix and CRC32B checksum.
     *
     * Returns the random string as-is when no prefix is configured.
     */
    public function decorateToken(string $random): string
    {
        if ($this->tokenPrefix === '') {
            return $random;
        }

        return $this->tokenPrefix.$random.hash('crc32b', $random);
    }

    /**
     * Extract the random part from a decorated token.
     *
     * Strips the prefix, validates the CRC32B checksum, and returns the random
     * part. Returns null if the token is malformed or the checksum doesn't match.
     */
    public function extractRandom(string $token): ?string
    {
        if ($this->tokenPrefix === '') {
            return $token;
        }

        $prefixLength = mb_strlen($this->tokenPrefix);

        if (! str_starts_with($token, $this->tokenPrefix)) {
            return null;
        }

        $withoutPrefix = mb_substr($token, $prefixLength);

        // CRC32B hex is always 8 characters
        if (mb_strlen($withoutPrefix) <= 8) {
            return null;
        }

        $random = mb_substr($withoutPrefix, 0, -8);
        $checksum = mb_substr($withoutPrefix, -8);

        if ($checksum !== hash('crc32b', $random)) {
            return null;
        }

        return $random;
    }
}
