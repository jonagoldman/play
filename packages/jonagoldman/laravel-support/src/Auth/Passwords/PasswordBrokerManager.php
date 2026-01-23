<?php

declare(strict_types=1);

namespace JonaGoldman\Support\Auth\Passwords;

use Illuminate\Contracts\Auth\PasswordBroker as PasswordBrokerContract;
use Illuminate\Contracts\Auth\PasswordBrokerFactory as PasswordBrokerFactoryContract;
use Illuminate\Contracts\Foundation\Application as App;
use InvalidArgumentException;

final class PasswordBrokerManager implements PasswordBrokerFactoryContract
{
    /**
     * Create a new PasswordBroker manager instance.
     */
    public function __construct(
        private App $app,
        private array $brokers = []
    ) {}

    /**
     * Dynamically call the default driver instance.
     *
     * @param  array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->broker()->{$method}(...$parameters);
    }

    /**
     * Get a password broker instance by name.
     */
    public function broker($name = null): PasswordBrokerContract
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->brokers[$name] ?? ($this->brokers[$name] = $this->resolve($name));
    }

    /**
     * Get the default password broker name.
     */
    public function getDefaultDriver(): string
    {
        return $this->app->make(\Illuminate\Contracts\Config\Repository::class)->get('auth.defaults.passwords');
    }

    /**
     * Set the default password broker name.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->app['config']['auth.defaults.passwords'] = $name;
    }

    /**
     * Resolve the given broker.
     *
     * @throws InvalidArgumentException
     */
    private function resolve(string $name): PasswordBrokerContract
    {
        $config = $this->getConfig($name);

        throw_if(is_null($config), InvalidArgumentException::class, "Password resetter [{$name}] is not defined.");

        // The password broker uses a token repository to validate tokens and send user
        // password e-mails, as well as validating that password reset process as an
        // aggregate service of sorts providing a convenient interface for resets.
        return new PasswordBroker(
            $this->app->make(\Illuminate\Contracts\Auth\Factory::class)->createUserProvider($config['provider'] ?? null),
            $this->createTokenRepository($config),
        );
    }

    private function createTokenRepository(array $config): \JonaGoldman\Support\Auth\Passwords\DatabaseTokenRepository
    {
        return new DatabaseTokenRepository(
            $this->app->make(\Illuminate\Database\ConnectionResolverInterface::class)->connection($config['connection'] ?? null),
            $this->app->make(\Illuminate\Hashing\HashManager::class),
            $this->app->make(\Illuminate\Contracts\Config\Repository::class)->get('app.key'),
            $config['table'],
            $config['expire'],
            $config['throttle'] ?? 0
        );
    }

    /**
     * Get the password broker configuration.
     */
    private function getConfig(string $name): array
    {
        return $this->app->make(\Illuminate\Contracts\Config\Repository::class)->get("auth.passwords.{$name}");
    }
}
