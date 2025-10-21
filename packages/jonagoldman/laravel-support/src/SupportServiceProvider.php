<?php

declare(strict_types=1);

namespace JonaGoldman\Support;

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
        //
    }
}
