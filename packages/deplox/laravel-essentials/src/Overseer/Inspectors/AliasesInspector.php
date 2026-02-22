<?php

declare(strict_types=1);

namespace Deplox\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;
use ReflectionClass;

final class AliasesInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        $appReflection = new ReflectionClass($app);
        $property = $appReflection->getProperty('abstractAliases');

        return $property->getValue($app);
    }
}
