<?php

declare(strict_types=1);

namespace Deplox\Shield;

use Closure;
use Deplox\Shield\Contracts\IsAuthToken;
use Deplox\Shield\Contracts\OwnsTokens;
use Deplox\Shield\Controllers\CsrfCookieController;
use Deplox\Shield\Guards\DynamicGuard;
use Deplox\Shield\Middlewares\StatefulFrontend;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

use function array_filter;
use function array_values;
use function hash;
use function mb_strlen;
use function mb_substr;
use function parse_url;

final class Shield
{
    /** @var ?list<string> */
    public readonly ?array $statefulDomains;

    /** @var Closure(Request): ?string */
    public readonly Closure $extractToken;

    /** @var Closure(IsAuthToken, Request): bool */
    public readonly Closure $validateToken;

    /** @var Closure(Authenticatable): bool */
    public readonly Closure $validateUser;

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $tokenModel
     * @param  class-string<Authenticatable>  $userModel
     * @param  list<string>  $guards
     * @param  ?list<string>  $statefulDomains  Explicit domains override config; null reads from config('shield.stateful')
     * @param  ?int  $defaultTokenExpiration  Default token expiration in seconds (null = no default, 0 = no expiration)
     * @param  array<string, class-string|null>  $middlewares
     * @param  ?Closure(Request): ?string  $extractToken
     * @param  ?Closure(IsAuthToken, Request): bool  $validateToken
     * @param  ?Closure(Authenticatable): bool  $validateUser
     */
    public function __construct(
        // Models (required)
        public readonly string $tokenModel,
        public readonly string $userModel,
        // Guards & domains
        public readonly array $guards = ['session'],
        ?array $statefulDomains = null,
        // Token lifecycle
        public readonly string $prefix = '',
        public readonly ?int $defaultTokenExpiration = 60 * 60 * 24 * 30,
        public readonly int $pruneDays = 30,
        public readonly int $lastUsedAtDebounce = 300,
        // Security & middleware
        public readonly bool $secureCookies = true,
        public readonly string $csrfCookiePath = '/auth/csrf-cookie',
        // Login rate limiting
        public readonly int $maxLoginAttempts = 5,
        public readonly int $loginDecaySeconds = 60,
        public readonly array $middlewares = [
            'encrypt_cookies' => \Illuminate\Cookie\Middleware\EncryptCookies::class,
            'validate_csrf_token' => \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'authenticate_session' => Middlewares\AuthenticateSession::class,
        ],
        // Extension callbacks (nullable params -> non-nullable properties)
        ?Closure $extractToken = null,
        ?Closure $validateToken = null,
        ?Closure $validateUser = null,
    ) {
        $this->statefulDomains = $statefulDomains;
        $this->extractToken = $extractToken ?? static fn (Request $request): ?string => $request->bearerToken();
        $this->validateToken = $validateToken ?? static fn (IsAuthToken $token, Request $request): bool => true;
        $this->validateUser = $validateUser ?? static fn (Authenticatable $user): bool => true;

        $this->validateParameters();
        $this->validateModels();
    }

    /**
     * Bind Shield as a singleton and configure the package.
     */
    public static function configure(Application $app, self $shield): void
    {
        $app->singleton(self::class, fn (): self => $shield);
    }

    /**
     * Get the host (with port, if non-standard) from the application URL.
     *
     * Returns an empty string when no application URL is configured.
     */
    public static function currentApplicationUrlWithPort(): string
    {
        $appUrl = config('app.url');

        if (! $appUrl) {
            return '';
        }

        $parsed = parse_url($appUrl);

        if (! isset($parsed['host'])) {
            return '';
        }

        $host = $parsed['host'];

        if (isset($parsed['port'])) {
            $host .= ':'.$parsed['port'];
        }

        return $host;
    }

    /**
     * Boot the auth package: register guard, middleware priority, secure cookies, and CSRF route.
     *
     * @param  Application|\Illuminate\Foundation\Application  $app
     * @param  Kernel|\Illuminate\Foundation\Http\Kernel  $kernel
     * @param  Auth|\Illuminate\Auth\AuthManager  $auth
     */
    public function boot(Application $app, Kernel $kernel, Auth $auth): void
    {
        $auth->viaRequest(
            'dynamic', fn (Request $request) => $app->make(DynamicGuard::class)($request)
        );

        $kernel->prependToMiddlewarePriority(StatefulFrontend::class);

        if ($this->secureCookies) {
            config([
                'session.http_only' => true,
                'session.same_site' => 'lax',
                'session.secure' => $app->isProduction(),
            ]);
        }

        Route::middleware(['api', StatefulFrontend::class])
            ->get($this->csrfCookiePath, CsrfCookieController::class);
    }

    /**
     * Decorate a raw random token with the configured prefix and CRC32B checksum.
     *
     * The CRC32B checksum is a non-cryptographic format validation layer that
     * allows early rejection of malformed tokens before hitting the database.
     * Actual security is provided by the 288-bit random entropy and SHA256 hash
     * stored in the database — the checksum is not a security boundary.
     *
     * Returns the random string as-is when no prefix is configured.
     */
    public function decorateToken(string $random): string
    {
        if ($this->prefix === '') {
            return $random;
        }

        return $this->prefix.$random.hash('crc32b', $random);
    }

    /**
     * Extract the random part from a decorated token.
     *
     * Strips the prefix and validates the CRC32B checksum as a format check to
     * reject obviously malformed tokens before performing a database lookup.
     * CRC32B is intentionally non-cryptographic here — the security boundary
     * is the SHA256 hash comparison in the database, not this checksum.
     *
     * Returns null if the token is malformed or the checksum doesn't match.
     */
    public function extractRandom(string $token): ?string
    {
        if ($this->prefix === '') {
            return $token;
        }

        $prefixLength = mb_strlen($this->prefix);

        if (! str_starts_with($token, $this->prefix)) {
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

    /**
     * Get the stateful domains, resolving from config when not explicitly set.
     *
     * @return list<string>
     */
    public function statefulDomains(): array
    {
        return $this->statefulDomains ?? array_values(array_filter(
            array_map(mb_trim(...), config('shield.stateful', [])),
        ));
    }

    /**
     * Determine if subdomain matching is enabled for stateful domains.
     */
    public function statefulSubdomains(): bool
    {
        return (bool) config('shield.stateful_subdomains', false);
    }

    private function validateParameters(): void
    {
        if ($this->defaultTokenExpiration !== null && $this->defaultTokenExpiration < 0) {
            throw new InvalidArgumentException('Default token expiration must be non-negative.');
        }

        if ($this->lastUsedAtDebounce < 1) {
            throw new InvalidArgumentException('Last-used-at debounce must be at least 1 second.');
        }
    }

    private function validateModels(): void
    {
        if (! class_exists($this->tokenModel)) {
            throw new InvalidArgumentException("Token model [{$this->tokenModel}] does not exist.");
        }

        if (! is_subclass_of($this->tokenModel, IsAuthToken::class)) {
            throw new InvalidArgumentException("Token model [{$this->tokenModel}] must implement the IsAuthToken contract.");
        }

        if (! class_exists($this->userModel)) {
            throw new InvalidArgumentException("User model [{$this->userModel}] does not exist.");
        }

        if (! is_subclass_of($this->userModel, OwnsTokens::class)) {
            throw new InvalidArgumentException("User model [{$this->userModel}] must implement the OwnsTokens contract.");
        }
    }
}
