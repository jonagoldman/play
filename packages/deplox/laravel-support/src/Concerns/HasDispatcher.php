<?php

declare(strict_types=1);

namespace Deplox\Support\Concerns;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;

trait HasDispatcher
{
    /**
     * @var Dispatcher
     */
    protected ?DispatcherContract $dispatcher;

    /**
     * Fire an event and call the listeners.
     */
    protected function dispatch($event, ...$params): ?array
    {
        $dispatcher = $this->getDispatcher();

        return $dispatcher->dispatch($event, ...$params);
    }

    protected function getDispatcher(): Dispatcher
    {
        return $this->dispatcher ??= app(DispatcherContract::class);
    }
}
