<?php

namespace App\Enums;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

enum TokenType: string
{
    case BEARER = 'bearer';
    case REMEMBER = 'remember';

    public function random(): string
    {
        return match ($this) {
            self::BEARER => sprintf('%s%s%s', Config::string('auth.guards.dynamic.token_prefix', ''), $secret = Str::random(40), hash('crc32b', $secret)),
            self::REMEMBER => Str::random(60),
        };
    }
}
