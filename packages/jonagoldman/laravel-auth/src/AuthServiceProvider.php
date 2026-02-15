<?php

declare(strict_types=1);

namespace JonaGoldman\Auth;

use Illuminate\Auth\RequestGuard;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use JonaGoldman\Auth\Guards\DynamicGuard;
use JonaGoldman\Auth\Middlewares\StatefulFrontend;
use Override;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var \Illuminate\Foundation\Application|\Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /** @var array<string, mixed> */
    private static array $pendingConfig = [];

    /**
     * Configure the auth package before registration.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $tokenModel
     * @param  class-string<\Illuminate\Contracts\Auth\Authenticatable>  $userModel
     * @param  list<string>  $guards
     * @param  list<string>  $statefulDomains
     */
    public static function configure(
        string $tokenModel,
        string $userModel,
        array $guards = ['session'],
        array $statefulDomains = [],
        bool $secureCookies = true,
        int $pruneDays = 30,
        int $lastUsedAtDebounce = 300,
    ): void {
        self::$pendingConfig = [
            'tokenModel' => $tokenModel,
            'userModel' => $userModel,
            'guards' => $guards,
            'statefulDomains' => $statefulDomains,
            'secureCookies' => $secureCookies,
            'pruneDays' => $pruneDays,
            'lastUsedAtDebounce' => $lastUsedAtDebounce,
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
                new DynamicGuard($auth, $app->make(AuthConfig::class)),
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
    }
}
