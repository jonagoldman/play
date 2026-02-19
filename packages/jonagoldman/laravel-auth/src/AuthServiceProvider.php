<?php

declare(strict_types=1);

namespace JonaGoldman\Auth;

use Closure;
use Illuminate\Auth\RequestGuard;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JonaGoldman\Auth\Actions\AuthenticateToken;
use JonaGoldman\Auth\Controllers\CsrfCookieController;
use JonaGoldman\Auth\Guards\DynamicGuard;
use JonaGoldman\Auth\Middlewares\StatefulFrontend;
use Override;

final class AuthServiceProvider extends ServiceProvider
{
    /** @var array<string, mixed> */
    private static array $pendingConfig = [];

    /**
     * Configure the auth package before registration.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $tokenModel
     * @param  class-string<\Illuminate\Contracts\Auth\Authenticatable>  $userModel
     * @param  list<string>  $guards
     * @param  list<string>  $statefulDomains
     * @param  ?int  $defaultTokenExpiration  Default token expiration in seconds (null = no default)
     * @param  ?Closure(\Illuminate\Contracts\Auth\Authenticatable): bool  $validateUser
     */
    public static function configure(
        string $tokenModel,
        string $userModel,
        array $guards = ['session'],
        array $statefulDomains = [],
        bool $secureCookies = true,
        int $pruneDays = 30,
        int $lastUsedAtDebounce = 300,
        ?int $defaultTokenExpiration = 60 * 60 * 24 * 30,
        ?Closure $validateUser = null,
        string $tokenPrefix = '',
        string $csrfCookiePath = '/auth/csrf-cookie',
    ): void {
        self::$pendingConfig = [
            'tokenModel' => $tokenModel,
            'userModel' => $userModel,
            'guards' => $guards,
            'statefulDomains' => $statefulDomains,
            'secureCookies' => $secureCookies,
            'pruneDays' => $pruneDays,
            'lastUsedAtDebounce' => $lastUsedAtDebounce,
            'defaultTokenExpiration' => $defaultTokenExpiration,
            'validateUser' => $validateUser,
            'tokenPrefix' => $tokenPrefix,
            'csrfCookiePath' => $csrfCookiePath,
        ];
    }

    #[Override]
    public function register(): void
    {
        $this->app->singleton(AuthConfig::class, function (): AuthConfig {
            return new AuthConfig(...self::$pendingConfig);
        });

        config([
            'auth.guards.dynamic' => array_merge([
                'driver' => 'dynamic',
                'provider' => 'users',
            ], config('auth.guards.dynamic', [])),
        ]);
    }

    /**
     * @param  Kernel|\Illuminate\Foundation\Http\Kernel  $kernel
     * @param  Auth|\Illuminate\Auth\AuthManager  $auth
     */
    public function boot(Kernel $kernel, Auth $auth): void
    {
        $auth->extend('dynamic', fn ($app, $name, array $config) => tap(
            new RequestGuard(
                new DynamicGuard(
                    $auth,
                    $app->make(AuthConfig::class),
                    $app->make(AuthenticateToken::class),
                    $app->make(DispatcherContract::class),
                ),
                $app['request'],
                $auth->createUserProvider($config['provider'] ?? null),
            ),
            fn (RequestGuard $guard) => $this->app->refresh('request', $guard, 'setRequest'),
        ));

        $kernel->prependToMiddlewarePriority(StatefulFrontend::class);

        /** @var AuthConfig $config */
        $config = $this->app->make(AuthConfig::class);

        if ($config->secureCookies) {
            config([
                'session.http_only' => true,
                'session.same_site' => 'lax',
                'session.secure' => $this->app->isProduction(),
            ]);
        }

        Route::middleware(['api', StatefulFrontend::class])
            ->get($config->csrfCookiePath, CsrfCookieController::class);
    }
}
