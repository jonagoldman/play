<?php

declare(strict_types=1);

namespace Deplox\Shield\Enums;

use Illuminate\Support\Str;

enum TokenType: string
{
    case Bearer = 'bearer';
    case Remember = 'remember';

    public function generate(): string
    {
        return match ($this) {
            self::Bearer => Str::random(48),
            self::Remember => Str::random(60),
        };
    }
}
