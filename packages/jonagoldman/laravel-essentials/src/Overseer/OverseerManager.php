<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Overseer;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Arrayable;
use JonaGoldman\Essentials\Overseer\Inspectors\AliasesInspector;
use JonaGoldman\Essentials\Overseer\Inspectors\BindingsInspector;
use JonaGoldman\Essentials\Overseer\Inspectors\EnvironmentInspector;
use JonaGoldman\Essentials\Overseer\Inspectors\ExtendersInspector;
use JonaGoldman\Essentials\Overseer\Inspectors\InstancesInspector;
use JonaGoldman\Essentials\Overseer\Inspectors\ProvidersInspector;
use JonaGoldman\Essentials\Overseer\Inspectors\RouterInspector;

/**
 * @implements Arrayable<array-key, mixed>
 **/
final class OverseerManager implements Arrayable
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    public function __construct(
        private Application $app,
    ) {}

    public function environment(): array
    {
        return new EnvironmentInspector()->inspect($this->app);
    }

    public function providers(): array
    {
        return new ProvidersInspector()->inspect($this->app);
    }

    public function aliases(): array
    {
        return new AliasesInspector()->inspect($this->app);
    }

    public function bindings(): array
    {
        return new BindingsInspector()->inspect($this->app);
    }

    public function instances(): array
    {
        return new InstancesInspector()->inspect($this->app);
    }

    public function extenders(): array
    {
        return new ExtendersInspector()->inspect($this->app);
    }

    public function router(): array
    {
        return new RouterInspector()->inspect($this->app);
    }

    public function toArray(): array
    {
        return collect([
            'environment' => $this->environment(),
            'providers' => $this->providers(),
            'aliases' => collect($this->aliases())->sortKeys(),
            'bindings' => collect($this->bindings())->sortKeys(),
            'instances' => collect($this->instances())->sortKeys(),
            'extenders' => collect($this->extenders())->sortKeys(),
            'router' => $this->router(),
        ])->toArray();
    }
}
