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

final class EssentialsServiceProvider extends ServiceProvider
{
    /**
     * @var \Illuminate\Foundation\Application|\Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    protected ?Settings $settings;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerConfig();
    }

    /**
     * Bootstrap any application services.
     */
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

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/essentials.php', 'essentials'
        );
    }

    protected function configure(): void
    {
        $this->settings = Settings::fromArray($this->app->make('config')->get('essentials'));

        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__.'/../config/essentials.php' => config_path('essentials.php'),
            ], 'essentials-config');
        }
    }

    protected function configureHttp(): void
    {
        /**
         * Configures Laravel Sleep Facade to be faked.
         * Avoid unexpected sleep during testing cases.
         */
        Sleep::fake($this->settings->fakeSleep);

        /**
         * Configures Laravel Http Facade to prevent stray requests.
         * Ensure every HTTP calls during tests have been explicitly faked.
         */
        Http::preventStrayRequests($this->settings->preventStrayRequests);
    }

    protected function configureUrls(): void
    {
        /**
         * Forces all generated URLs to use `https://`.
         * Ensures all traffic uses secure connections by default.
         */
        URL::forceHttps($this->settings->forceHttps);
    }

    protected function configureVite(): void
    {
        /**
         * Configures Laravel Vite to preload assets more aggressively.
         * Improves front-end load times and user experience.
         */
        Vite::useAggressivePrefetching($this->settings->aggressivePrefetching);
    }

    protected function configureDates(): void
    {
        /**
         * Uses `CarbonImmutable` instead of mutable date objects across your app.
         * Prevents unexpected date mutations and improves predictability.
         */
        if ($this->settings->immutableDates) {
            Date::use(CarbonImmutable::class);
        }
    }

    protected function configureModels(): void
    {
        /**
         * Disables Laravel's mass assignment protection globally (opt-in).
         * Useful in trusted or local environments where you want to skip defining `$fillable`.
         */
        Model::unguard($this->settings->unguardModel);

        /**
         * Improves how Eloquent handles undefined attributes, lazy loading, and invalid assignments.
         *
         * - Accessing a missing attribute throws an error.
         * - Lazy loading is blocked unless explicitly allowed.
         * - Setting undefined attributes throws instead of failing silently.
         *
         * Avoids subtle bugs and makes model behavior easier to reason about.
         */
        Model::shouldBeStrict($this->settings->strictModel);

        /**
         * Automatically eager loads relationships defined in the model's `$with` property.
         * Reduces N+1 query issues and improves performance without needing `with()` everywhere.
         */
        Model::automaticallyEagerLoadRelationships($this->settings->automaticEagerLoadRelationships);
    }

    protected function configureSchemas(): void
    {
        /**
         * Set the default string length for migrations.
         */
        Builder::defaultStringLength($this->settings->defaultStringLength);

        /**
         * Set the default morph key type for migrations.
         */
        Builder::defaultMorphKeyType($this->settings->defaultMorphKeyType);
    }

    protected function configureCommands(): void
    {
        /**
         * Blocks potentially destructive Artisan commands in production (e.g., `migrate:fresh`).
         * Prevents accidental data loss and adds a safety net in sensitive environments.
         */
        DB::prohibitDestructiveCommands($this->settings->prohibitDestructiveCommands && $this->app->isProduction());
    }

    protected function configurePasswords(): void
    {
        if ($this->settings->setDefaultPasswords) {
            Password::defaults(function (): Password {
                $rule = Password::min(8)->max($this->settings->defaultStringLength);

                return $this->app->isProduction()
                    ? $rule->mixedCase()->uncompromised()
                    : $rule;
            });
        }

    }
}
