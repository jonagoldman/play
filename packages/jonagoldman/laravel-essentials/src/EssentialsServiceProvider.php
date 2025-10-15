<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials;

use Illuminate\Support\ServiceProvider;
use JonaGoldman\Essentials\Dogma\DogmaManager;
use JonaGoldman\Essentials\Overseer\OverseerManager;
use Override;

final class EssentialsServiceProvider extends ServiceProvider
{
    /**
     * @var \Illuminate\Foundation\Application|\Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/essentials.php', 'essentials'
        );

        $this->app->singleton('overseer', fn ($app): OverseerManager => new OverseerManager($app));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/essentials.php' => config_path('essentials.php'),
            ], 'essentials-config');
        }

        $dogma = new DogmaManager(
            EssentialsConfig::fromArray($this->app->make('config')->get('essentials'))
        );

        $dogma->apply();
    }
}
