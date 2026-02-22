<?php

declare(strict_types=1);

namespace Deplox\Support\Auth\Passwords;

use Illuminate\Auth\Passwords\PasswordResetServiceProvider as BasePasswordResetServiceProvider;

/**
 * @property-read \Orvital\Core\Foundation\Application $app
 */
final class PasswordResetServiceProvider extends BasePasswordResetServiceProvider
{
    #[\Override]
    protected function registerPasswordBroker(): void
    {
        $this->app->singleton('auth.password', fn($app): \Deplox\Support\Auth\Passwords\PasswordBrokerManager => new PasswordBrokerManager($app));

        $this->app->bind('auth.password.broker', fn($app) => $app->make('auth.password')->broker());
    }
}
