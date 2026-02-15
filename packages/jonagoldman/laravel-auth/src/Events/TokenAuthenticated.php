<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Events;

use Illuminate\Database\Eloquent\Model;

final class TokenAuthenticated
{
    public function __construct(
        public readonly Model $token,
    ) {}
}
