<?php

declare(strict_types=1);

namespace Deplox\Support\Auth\Passwords;

use Illuminate\Auth\Passwords\PasswordResetServiceProvider as BasePasswordResetServiceProvider;
use Override;

/**
 * Opt-in replacement for Laravel's default password reset broker.
 *
 * Register this provider manually in bootstrap/providers.php to swap in
 * Deplox\Support\Auth\Passwords\PasswordBrokerManager. Not auto-discovered.
 */
final class PasswordResetServiceProvider extends BasePasswordResetServiceProvider
{
    #[Override]
    protected function registerPasswordBroker(): void
    {
        $this->app->singleton('auth.password', fn ($app): PasswordBrokerManager => new PasswordBrokerManager($app));

        $this->app->bind('auth.password.broker', fn ($app) => $app->make('auth.password')->broker());
    }
}
