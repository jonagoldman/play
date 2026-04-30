<?php

declare(strict_types=1);

namespace Deplox\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionException;

final class ExtendersInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        try {
            $property = new ReflectionClass($app)->getProperty('extenders');
        } catch (ReflectionException) {
            return [];
        }

        return Arr::map($property->getValue($app), fn ($value): int => count($value));
    }
}
