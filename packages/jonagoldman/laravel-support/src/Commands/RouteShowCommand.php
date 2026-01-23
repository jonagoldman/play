<?php

declare(strict_types=1);

namespace JonaGoldman\Support\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionFunction;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

final class RouteShowCommand extends Command
{
    protected $name = 'route:show';

    protected $description = 'Show all registered routes';

    /**
     * The table headers for the command.
     */
    private array $headers = ['Domain', 'Method', 'URI', 'Name', 'Action', 'Middleware'];

    /**
     * The verb colors for the command.
     */
    private array $verbColors = [
        'ANY' => 'red',
        'GET' => 'blue',
        'HEAD' => '#6C7280',
        'OPTIONS' => '#6C7280',
        'POST' => 'yellow',
        'PUT' => 'yellow',
        'PATCH' => 'yellow',
        'DELETE' => 'red',
    ];

    /**
     * Create a new route command instance.
     *
     * @return void
     */
    public function __construct(/**
     * The router instance.
     */
    private readonly Router $router)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->router->flushMiddlewareGroups();

        $routes = collect($this->router->getRoutes());

        if ($routes->isEmpty()) {
            return $this->components->error("Your application doesn't have any routes.");
        }

        $routes = $this->compileRoutes($routes);

        if ($routes->isEmpty()) {
            return $this->components->error("Your application doesn't have any routes matching the given criteria.");
        }

        $this->option('json')
            ? $this->forJson($routes)
            : $this->forTable($routes);
    }

    /**
     * Compile the routes into a displayable format.
     */
    private function compileRoutes(Collection $routes): Collection
    {
        $routes = $this->filterRoutes($routes);

        $routes = $this->sortRoutes($routes);

        $routes = $this->pluckColumns($routes);

        return $routes;
    }

    /**
     * Filter the route by URI and / or name.
     */
    private function shouldIncludeRoute(array $route): bool
    {
        return !($this->option('name') && ! Str::contains((string) $route['name'], $this->option('name')) || $this->option('uri') && ! Str::contains($route['uri'], $this->option('uri')) || $this->option('method') && ! Str::contains($route['method'], mb_strtoupper($this->option('method'))) || $this->option('domain') && ! Str::contains((string) $route['domain'], $this->option('domain')) || ! $this->option('vendor') && $route['vendor']);
    }

    /**
     * Get the route information.
     */
    private function getRouteInformation(Route $route): array
    {
        return [
            'domain' => $route->domain(),
            'method' => $route->methods() === Router::$verbs ? 'ANY' : implode('|', $route->methods()),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $this->getAction($route),
            'middleware' => $this->getMiddleware($route),
            'vendor' => $this->isVendorRoute($route),
        ];
    }

    /**
     * Filter routes using the provided options.
     */
    private function filterRoutes(Collection $routes): Collection
    {
        return $routes->map(fn(Route $route): array => $this->getRouteInformation($route))->filter(fn($route): bool => $this->shouldIncludeRoute($route));
    }

    /**
     * Sort routes by a given element.
     */
    private function sortRoutes(Collection $routes): Collection
    {
        $column = $this->option('sort') ?? 'uri';

        $options = SORT_NATURAL;

        if ($column === 'middleware') {
            $column = function (array $route, int $key): string {
                asort($route['middleware']);

                return implode(',', $route['middleware']);
            };

            $options = SORT_NUMERIC;
        }

        $routes = $routes->sortBy($column, $options);

        if ((bool) $this->option('reverse')) {
            return $routes->reverse();
        }

        return $routes;
    }

    /**
     * Remove unnecessary columns from routes.
     */
    private function pluckColumns(Collection $routes): Collection
    {
        $columns = $this->getColumns();

        return $routes->map(fn($route) => Arr::only($route, $columns));
    }

    /**
     * Get the action for the route.
     */
    private function getAction(Route $route): string
    {
        $action = $route->getActionMethod();
        $controller = $route->getControllerClass();

        if ($action === $controller) {
            return match ($controller) {
                \Illuminate\Routing\RedirectController::class => 'Redirect',
                \Illuminate\Routing\ViewController::class => 'View',
                default => 'Invokable',
            };
        }

        return $action;
    }

    /**
     * Get the middleware for the route.
     */
    private function getMiddleware(Route $route, bool $useShortHand = true): array
    {
        $map = array_flip($this->router->getMiddleware());

        return collect($this->router->gatherRouteMiddleware($route))->map(function ($middleware) use ($map, $useShortHand) {
            $middleware = $middleware instanceof Closure ? 'Closure' : $middleware;

            // show the middleware short-hand name
            if ($useShortHand) {
                $key = Str::before($middleware, ':');

                if (Arr::exists($map, $key)) {
                    $middleware = Str::replace($key, $map[$key], $middleware);
                }
            }

            return $middleware;
        })->all();
    }

    /**
     * Determine if the route has been defined outside of the application.
     */
    private function isVendorRoute(Route $route): bool
    {
        if ($route->action['uses'] instanceof Closure) {
            $path = new ReflectionFunction($route->action['uses'])->getFileName();
        } elseif (is_string($route->action['uses']) && str_contains($route->action['uses'], 'SerializableClosure')) {
            return false;
        } elseif (is_string($route->action['uses'])) {
            if ($this->isFrameworkController($route)) {
                return false;
            }

            $path = new ReflectionClass($route->getControllerClass())->getFileName();
        } else {
            return false;
        }

        return str_starts_with($path, $this->laravel->basePath('vendor'));
    }

    /**
     * Determine if the route uses a framework controller.
     */
    private function isFrameworkController(Route $route): bool
    {
        return in_array($route->getControllerClass(), [\Illuminate\Routing\RedirectController::class, \Illuminate\Routing\ViewController::class], true);
    }

    /**
     * Get the table headers for the visible columns.
     */
    private function getHeaders(): array
    {
        return Arr::only($this->headers, array_keys($this->getColumns()));
    }

    /**
     * Get the column names to show (lowercase table headers).
     */
    private function getColumns(): array
    {
        return array_map('strtolower', $this->headers);
    }

    /**
     * Parse the column list.
     */
    private function parseColumns(array $columns): array
    {
        $results = [];

        foreach ($columns as $column) {
            if (str_contains((string) $column, ',')) {
                $results = array_merge($results, explode(',', (string) $column));
            } else {
                $results[] = $column;
            }
        }

        return array_map('strtolower', $results);
    }

    private function forJson(Collection $routes): void
    {
        $this->line($routes->values()->toJson());
    }

    /**
     * Convert the given routes to table.
     */
    private function forTable(Collection $routes): void
    {
        $table = new Table($this->output);

        $headers = ['Method', 'URI', 'Name', 'Action', 'Middleware'];

        $table->setHeaders($headers);

        $rows = $routes->map(function (array $route): array {
            $method = $this->formatMethod($route);
            $uri = $this->formatUri($route);
            $action = $this->formatAction($route);

            return [
                'method' => $method,
                'uri' => $uri,
                'name' => $route['name'],
                'action' => $action,
                'middleware' => implode(', ', $route['middleware']),
            ];
        })->all();

        $table->setRows($rows);

        $table->render();
    }

    private function formatMethod(array $route): ?string
    {
        return Str::of($route['method'])->explode('|')->map(fn($method): string => sprintf('<fg=%s>%s</>', $this->verbColors[$method] ?? 'default', $method))->implode('<fg=#6C7280>|</>');
    }

    private function formatUri(array $route): ?string
    {
        $uri = $route['uri'];

        if ($route['domain']) {
            $uri = $route['domain'].'/'.mb_ltrim($uri, '/');
        }

        return preg_replace('#({[^}]+})#', '<fg=yellow>$1</>', (string) $uri);
    }

    private function formatAction(array $route): ?string
    {
        $action = $route['action'];

        if (in_array($action, ['Closure', 'Invokable', 'View', 'Redirect'])) {
            return "<fg=yellow>{$action}</>";
        }

        return $action;
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        $columns = implode(', ', $this->getColumns());

        return [
            ['json', null, InputOption::VALUE_NONE, 'Output routes as JSON'],
            ['domain', null, InputOption::VALUE_OPTIONAL, 'Filter routes by domain'],
            ['method', null, InputOption::VALUE_OPTIONAL, 'Filter routes by method'],
            ['name', null, InputOption::VALUE_OPTIONAL, 'Filter routes by name'],
            ['uri', null, InputOption::VALUE_OPTIONAL, 'Filter routes by uri'],
            ['sort', null, InputOption::VALUE_OPTIONAL, 'Sort routes by column ('.$columns.') in descending order', 'uri'],
            ['reverse', null, InputOption::VALUE_NONE, 'Reverse the sort order of the routes'],
            ['vendor', null, InputOption::VALUE_NONE, 'Include routes defined by vendor packages'],
        ];
    }
}
