<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

enum TokenType: string
{
    case BEARER = 'bearer';
    case REMEMBER = 'remember';

    public function generate(): string
    {
        return match ($this) {
            self::BEARER => sprintf('%s%s', $secret = Str::random(40), hash('crc32b', $secret)),
            self::REMEMBER => Str::random(60),
        };
    }
}
