<?php

declare(strict_types=1);

namespace Deplox\Shield\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Deplox\Shield\Shield;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTokens
{
    use CreatesTokens;

    public function token(): HasOne
    {
        return $this->tokens()->one()->latestOfMany();
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(app(Shield::class)->tokenModel);
    }
}
