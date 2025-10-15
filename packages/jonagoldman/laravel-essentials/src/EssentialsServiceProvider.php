<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Sleep;
use Illuminate\Validation\Rules\Password;
use JonaGoldman\Essentials\Overseer\OverseerManager;
use Override;

final class EssentialsServiceProvider extends ServiceProvider
{
    /**
     * @var \Illuminate\Foundation\Application|\Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    private ?EssentialsConfig $config = null;

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
        $this->configure();
        $this->configureHttp();
        $this->configureUrls();
        $this->configureVite();
        $this->configureDates();
        $this->configureModels();
        $this->configureSchemas();
        $this->configureCommands();
        $this->configurePasswords();
    }

    private function configure(): void
    {
        $this->config = EssentialsConfig::fromArray($this->app->make('config')->get('essentials'));

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/essentials.php' => config_path('essentials.php'),
            ], 'essentials-config');
        }
    }

    private function configureHttp(): void
    {
        /**
         * Configures Laravel Sleep Facade to be faked.
         * Avoid unexpected sleep during testing cases.
         */
        Sleep::fake($this->config->fakeSleep);

        /**
         * Configures Laravel Http Facade to prevent stray requests.
         * Ensure every HTTP calls during tests have been explicitly faked.
         */
        Http::preventStrayRequests($this->config->preventStrayRequests);
    }

    private function configureUrls(): void
    {
        /**
         * Forces all generated URLs to use `https://`.
         * Ensures all traffic uses secure connections by default.
         */
        URL::forceHttps($this->config->forceHttps);
    }

    private function configureVite(): void
    {
        /**
         * Configures Laravel Vite to preload assets more aggressively.
         * Improves front-end load times and user experience.
         */
        Vite::useAggressivePrefetching();
    }

    private function configureDates(): void
    {
        /**
         * Uses `CarbonImmutable` instead of mutable date objects across your app.
         * Prevents unexpected date mutations and improves predictability.
         */
        if ($this->config->immutableDates) {
            Date::use(CarbonImmutable::class);
        }
    }

    private function configureModels(): void
    {
        /**
         * Disables Laravel's mass assignment protection globally (opt-in).
         * Useful in trusted or local environments where you want to skip defining `$fillable`.
         */
        Model::unguard($this->config->unguardModel);

        /**
         * Improves how Eloquent handles undefined attributes, lazy loading, and invalid assignments.
         *
         * - Accessing a missing attribute throws an error.
         * - Lazy loading is blocked unless explicitly allowed.
         * - Setting undefined attributes throws instead of failing silently.
         *
         * Avoids subtle bugs and makes model behavior easier to reason about.
         */
        Model::shouldBeStrict($this->config->strictModel);

        /**
         * Automatically eager loads relationships defined in the model's `$with` property.
         * Reduces N+1 query issues and improves performance without needing `with()` everywhere.
         */
        Model::automaticallyEagerLoadRelationships($this->config->automaticEagerLoadRelationships);
    }

    private function configureSchemas(): void
    {
        /**
         * Set the default string length for migrations.
         */
        Builder::defaultStringLength($this->config->defaultStringLength);

        /**
         * Set the default morph key type for migrations.
         */
        Builder::defaultMorphKeyType($this->config->defaultMorphKeyType);
    }

    private function configureCommands(): void
    {
        /**
         * Blocks potentially destructive Artisan commands in production (e.g., `migrate:fresh`).
         * Prevents accidental data loss and adds a safety net in sensitive environments.
         */
        DB::prohibitDestructiveCommands($this->config->prohibitDestructiveCommands && $this->app->isProduction());
    }

    private function configurePasswords(): void
    {
        if ($this->config->setDefaultPasswords) {
            Password::defaults(function (): Password {
                $rule = Password::min(8)->max($this->config->defaultStringLength);

                return $this->app->isProduction()
                    ? $rule->mixedCase()->uncompromised()
                    : $rule;
            });
        }

    }
}
