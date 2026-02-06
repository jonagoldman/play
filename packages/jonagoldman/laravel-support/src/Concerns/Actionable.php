<?php

declare(strict_types=1);

namespace JonaGoldman\Support\Concerns;

use Illuminate\Container\Container;

trait Actionable
{
    public function __invoke(...$arguments)
    {
        return $this->execute(...$arguments);
    }

    public static function make(array $parameters = []): static
    {
        return Container::getInstance()->make(static::class, $parameters);
    }

    public static function run(...$arguments)
    {
        return static::make()->execute(...$arguments);
    }

    public function execute() {}
}
