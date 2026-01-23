<?php

declare(strict_types=1);

namespace JonaGoldman\Support\Concerns;

use Illuminate\Container\Container;

trait Makeable
{
    public static function make(array $parameters = []): static
    {
        return Container::getInstance()->make(static::class, $parameters);
    }
}
