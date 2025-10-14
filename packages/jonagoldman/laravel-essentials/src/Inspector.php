<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials;

use Illuminate\Contracts\Foundation\Application as App;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * @implements Arrayable<array-key, mixed>
 **/
final class Inspector implements Arrayable
{
    /**
     * The Laravel scaffold version.
     */
    protected const string VERSION = '12.7.0';

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @param  array<string>  $data
     */
    public function __construct(
        protected App $app,
        protected Composer $composer,
        protected array $data = [],
    ) {}

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return collect([
            'environment' => $this->getEnvironment(),
            'providers' => $this->getProviders(),
            'aliases' => collect($this->getAliases())->sortKeys(),
            'bindings' => collect($this->getBindings())->sortKeys(),
            'instances' => collect($this->getInstances())->sortKeys(),
            'extenders' => collect($this->getExtenders())->sortKeys(),
            'router' => $this->getRouter(),
        ])->toArray();
    }

    /**
     * @return array<string>
     */
    public function getEnvironment(): array
    {
        return [
            'php' => implode('.', [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]),
            'laravel' => self::VERSION,
            'framework' => $this->app->version(),
            'composer' => $this->composer->getVersion() ?? '-',
            'database' => Str::before($this->app['db']->select('select version() as version')[0]->{'version'}, ' ('),
            // 'MySQL' => $this->app['db']->select('select version()')[0]->{'version()'},
        ];
    }

    /**
     * @return array<string>
     */
    public function getProviders(): array
    {
        $registered = array_keys($this->app->getLoadedProviders());

        $unresolved = [...array_diff(array_keys(array_flip($this->app->getDeferredServices())), $registered)];

        $services = array_filter($this->app->getDeferredServices(), fn ($provider) => in_array($provider, $unresolved));

        $unresolved = array_fill_keys($unresolved, ['loaded' => false, 'deferred' => true, 'provides' => []]);

        foreach ($services as $binding => $provider) {
            $unresolved[$provider]['provides'][] = $binding;
        }

        $registered = array_fill_keys($registered, ['loaded' => true, 'deferred' => false, 'provides' => []]);

        foreach ($registered as $key => $value) {
            $provider = $this->app->getProvider($key);

            $registered[$key]['deferred'] = $provider->isDeferred();
            $registered[$key]['provides'] = $provider->provides();
        }

        $data = array_merge($registered, $unresolved);

        return $data;
    }

    /**
     * @return array<string>
     */
    public function getAliases(): array
    {
        $app = new ReflectionClass($this->app);
        $property = $app->getProperty('abstractAliases');

        $data = $property->getValue($this->app);

        return $data;
    }

    /**
     * @return array<string>
     */
    public function getBindings(): array
    {
        $data = Arr::map($this->app->getBindings(), function ($concrete, $abstract) {
            return ['resolved' => $this->app->resolved($abstract), 'singleton' => $concrete['shared']];
        });

        return $data;
    }

    /**
     * @return array<string>
     */
    public function getInstances(): array
    {
        $app = new ReflectionClass($this->app);
        $property = $app->getProperty('instances');

        $data = Arr::map($property->getValue($this->app), function ($instance) {
            return is_string($instance) ? $instance : get_class($instance);
        });

        return $data;
    }

    /**
     * @return array<string>
     */
    public function getExtenders(): array
    {
        $app = new ReflectionClass($this->app);
        $property = $app->getProperty('extenders');

        $data = Arr::map($property->getValue($this->app), function ($value) {
            return count($value);
        });

        return $data;
    }

    /**
     * @return array<string>
     */
    public function getRouter(): array
    {
        /** @var \Illuminate\Routing\Router */
        $router = $this->app['router'];

        /** @var \Illuminate\Routing\RouteCollection */
        $routes = $router->getRoutes();

        $data = [
            'routes' => [],
            'middlewares' => [
                'groups' => $router->getMiddlewareGroups(),
                'aliases' => $router->getMiddleware(),
                'priority' => $router->middlewarePriority,
            ],
        ];

        foreach ($routes->getRoutes() as $route) {

            $method = $route->methods() === $router::$verbs ? 'ANY' : implode('|', $route->methods());
            $uri = implode('/', array_filter([$route->getDomain(), $route->uri()]));

            Arr::set($data['routes'], $uri.'.'.$method, [
                'name' => $route->getName(),
                'method' => $method,
                'uri' => $uri,
                'action' => $route->getAction(),
                'fallback' => $route->isFallback,
                'defaults' => $route->defaults,
                'wheres' => $route->wheres,
                'bindingFields' => $route->bindingFields(),
                'lockSeconds' => $route->locksFor(),
                'waitSeconds' => $route->waitsFor(),
                'withTrashed' => $route->allowsTrashedBindings(),
            ]);
        }

        return $data;
    }
}
