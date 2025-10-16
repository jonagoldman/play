<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;

final class RouterInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        /** @var \Illuminate\Routing\Router */
        $router = $app->make(\Illuminate\Routing\Router::class);

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
