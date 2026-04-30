<?php

declare(strict_types=1);

namespace Deplox\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;
use ReflectionClass;
use ReflectionException;

final class AliasesInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        try {
            $property = new ReflectionClass($app)->getProperty('abstractAliases');
        } catch (ReflectionException) {
            return [];
        }

        return $property->getValue($app);
    }
}
