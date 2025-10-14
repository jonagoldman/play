<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Facades;

use Illuminate\Support\Facades\Facade;

class Inspector extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \JonaGoldman\Essentials\Inspector::class;
    }
}
