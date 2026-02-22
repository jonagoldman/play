<?php

declare(strict_types=1);

namespace Deplox\Shield\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

interface HasTokens extends OwnsTokens
{
    public function token(): HasOne;

    public function tokens(): HasMany;
}
