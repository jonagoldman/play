<?php

declare(strict_types=1);

namespace Deplox\Support;

use Illuminate\Support\ServiceProvider;
use Override;

final class SupportServiceProvider extends ServiceProvider
{
    /**
     * @var \Illuminate\Foundation\Application|\Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'support');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/support'),
            ], 'laravel-support-translations');
        }
    }
}
