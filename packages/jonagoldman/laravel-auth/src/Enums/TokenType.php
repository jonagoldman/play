<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Enums;

use Illuminate\Support\Str;

enum TokenType: string
{
    case BEARER = 'bearer';
    case REMEMBER = 'remember';

    public function generate(): string
    {
        return match ($this) {
            self::BEARER => Str::random(48),
            self::REMEMBER => Str::random(60),
        };
    }
}
