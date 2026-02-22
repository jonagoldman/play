<?php

declare(strict_types=1);

namespace Deplox\Shield\Events;

use Illuminate\Database\Eloquent\Model;
use Deplox\Shield\Contracts\IsAuthToken;

final class TokenAuthenticated
{
    public function __construct(
        public readonly Model&IsAuthToken $token,
    ) {}
}
