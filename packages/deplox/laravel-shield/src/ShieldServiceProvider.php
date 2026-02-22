<?php

declare(strict_types=1);

namespace Deplox\Shield;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Override;

final class ShieldServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
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
        $this->app->make(Shield::class)->boot($this->app, $kernel, $auth);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'laravel-shield-migrations');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations/morph' => database_path('migrations'),
        ], 'laravel-shield-morph-migrations');
    }
}
