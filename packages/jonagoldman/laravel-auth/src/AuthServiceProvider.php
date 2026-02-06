<?php

declare(strict_types=1);

namespace JonaGoldman\Auth;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
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
     * Bootstrap any application services.
     *
     * @param  Kernel|\Illuminate\Foundation\Http\Kernel  $kernel
     * @param  Auth|\Illuminate\Auth\AuthManager  $auth
     */
    public function boot(Kernel $kernel, Auth $auth): void
    {
        // Register a new callback based request guard
        $auth->viaRequest('dynamic', function (Request $request) {
            return app(DynamicGuard::class)->user($request);
        });

        $kernel->prependToMiddlewarePriority(StatefulFrontend::class);
    }
}
