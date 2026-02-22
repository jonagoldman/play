<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface HasMorphTokens extends OwnsTokens
{
    public function token(): MorphOne;

    public function tokens(): MorphMany;
}
