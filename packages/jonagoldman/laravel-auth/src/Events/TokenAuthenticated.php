<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Events;

use Illuminate\Database\Eloquent\Model;
use JonaGoldman\Auth\Contracts\IsAuthToken;

final class TokenAuthenticated
{
    public function __construct(
        public readonly Model&IsAuthToken $token,
    ) {}
}
