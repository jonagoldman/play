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
     * @var \Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application
     */
    protected $app;

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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/essentials.php' => config_path('essentials.php'),
            ], 'essentials-config');
        }

        $config = config('essentials');

        $this->configureHttp(
            $config['fake_sleep'],
            $config['prevent_stray_requests']
        );

        $this->configureUrls(
            $config['force_https']
        );

        $this->configureVite(
            $config['aggressive_prefetching']
        );

        $this->configureDates(
            $config['immutable_dates']
        );

        $this->configureModels(
            $config['model_unguard'],
            $config['model_strict'],
            $config['model_automatic_eager_load_relationships']
        );

        $this->configureSchemas(
            $config['default_string_length'],
            $config['default_morph_key_type']
        );

        $this->configureCommands(
            $config['prohibit_destructive_commands']
        );

        $this->configurePasswords(
            $config['set_default_passwords'],
            $config['default_string_length']
        );
    }

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/essentials.php', 'essentials'
        );
    }

    protected function configureHttp(bool $fakeSleep = true, bool $preventStrayRequests = true): void
    {
        /**
         * Configures Laravel Sleep Facade to be faked.
         * Avoid unexpected sleep during testing cases.
         */
        Sleep::fake($fakeSleep);

        /**
         * Configures Laravel Http Facade to prevent stray requests.
         * Ensure every HTTP calls during tests have been explicitly faked.
         */
        Http::preventStrayRequests($preventStrayRequests);
    }

    protected function configureUrls(bool $forceHttps = true): void
    {
        /**
         * Forces all generated URLs to use `https://`.
         * Ensures all traffic uses secure connections by default.
         */
        URL::forceHttps($forceHttps);
    }

    protected function configureVite(bool $aggressivePrefetching = true): void
    {
        /**
         * Configures Laravel Vite to preload assets more aggressively.
         * Improves front-end load times and user experience.
         */
        Vite::useAggressivePrefetching($aggressivePrefetching);
    }

    protected function configureDates(bool $immutableDates = true): void
    {
        /**
         * Uses `CarbonImmutable` instead of mutable date objects across your app.
         * Prevents unexpected date mutations and improves predictability.
         */
        if ($immutableDates) {
            Date::use(CarbonImmutable::class);
        }
    }

    protected function configureModels(bool $modelStrict = true, bool $modelAutomaticEagerLoadRelationships = false, bool $modelUnguard = false): void
    {
        /**
         * Improves how Eloquent handles undefined attributes, lazy loading, and invalid assignments.
         *
         * - Accessing a missing attribute throws an error.
         * - Lazy loading is blocked unless explicitly allowed.
         * - Setting undefined attributes throws instead of failing silently.
         *
         * Avoids subtle bugs and makes model behavior easier to reason about.
         */
        Model::shouldBeStrict($modelStrict);

        /**
         * Automatically eager loads relationships defined in the model's `$with` property.
         * Reduces N+1 query issues and improves performance without needing `with()` everywhere.
         */
        Model::automaticallyEagerLoadRelationships($modelAutomaticEagerLoadRelationships);

        /**
         * Disables Laravel's mass assignment protection globally (opt-in).
         * Useful in trusted or local environments where you want to skip defining `$fillable`.
         */
        Model::unguard($modelUnguard);
    }

    protected function configureSchemas(int $defaultStringLength = 255, string $defaultMorphKeyType = 'int'): void
    {
        /**
         * Set the default string length for migrations.
         */
        Builder::defaultStringLength($defaultStringLength);

        /**
         * Set the default morph key type for migrations.
         */
        Builder::defaultMorphKeyType($defaultMorphKeyType);
    }

    protected function configureCommands(bool $prohibitDestructiveCommands = true): void
    {
        /**
         * Blocks potentially destructive Artisan commands in production (e.g., `migrate:fresh`).
         * Prevents accidental data loss and adds a safety net in sensitive environments.
         */
        DB::prohibitDestructiveCommands($prohibitDestructiveCommands && $this->app->isProduction());
    }

    protected function configurePasswords(bool $setDefaultPasswords = true, int $defaultStringLength = 255): void
    {
        if ($setDefaultPasswords) {
            Password::defaults(function () use ($defaultStringLength): Password {
                $rule = Password::min(8)->max($defaultStringLength);

                return $this->app->isProduction()
                    ? $rule->mixedCase()->uncompromised()
                    : $rule;
            });
        }

    }
}
