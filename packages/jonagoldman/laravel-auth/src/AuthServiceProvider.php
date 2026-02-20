<?php

declare(strict_types=1);

namespace JonaGoldman\Auth;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JonaGoldman\Auth\Controllers\CsrfCookieController;
use JonaGoldman\Auth\Guards\DynamicGuard;
use JonaGoldman\Auth\Middlewares\StatefulFrontend;
use Override;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application
     */
    protected $app;

    private static ?AuthConfig $pendingConfig = null;

    public static function configure(AuthConfig $config): void
    {
        self::$pendingConfig = $config;
    }

    #[Override]
    public function register(): void
    {
        $this->app->singleton(AuthConfig::class, fn (): AuthConfig => self::$pendingConfig);

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
        $auth->viaRequest(
            'dynamic',
            fn (Request $request) => $this->app->make(DynamicGuard::class)($request)
        );

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

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'laravel-auth-migrations');
    }
}
