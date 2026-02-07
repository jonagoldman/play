<?php

declare(strict_types=1);

namespace App\Providers;

use App\ApiRouteRegistrar;
use App\Models\Token;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\ServiceProvider;
use JonaGoldman\Auth\AuthServiceProvider;
use Override;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * @var \Illuminate\Foundation\Application|\Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    #[Override]
    public function register(): void
    {
        JsonApiResource::configure(version: '2.0.0');

        AuthServiceProvider::configure(
            tokenModel: Token::class,
            userModel: User::class,
            guards: ['session'],
            statefulDomains: ['localhost', 'localhost:3000', '127.0.0.1', '127.0.0.1:8000', '::1', 'play.ddev.site'],
        );
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerMorphMap();
        $this->registerEventsListeners();
    }

    protected function registerRoutes(): void
    {
        /** @var \Illuminate\Routing\RouteRegistrar|\Illuminate\Routing\Router $router */
        $router = $this->app->make('router');

        /** @var \Illuminate\Config\Repository $config */
        $config = $this->app->make('config');

        $router->prefix('api')
            ->middleware(['api', 'throttle:api'])
            ->group(function ($router): void {
                /** @var \Illuminate\Routing\Router $router */
                $router
                    ->tap(new ApiRouteRegistrar);
            });
    }

    protected function registerMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'token' => Token::class,
        ]);
    }

    protected function registerEventsListeners(): void
    {
        /** @var \Illuminate\Events\Dispatcher $events */
        // $events = $this->app->make('events');

        // $events->listen(function (MyEvent $event): void {
        //     //
        // });
    }
}
