<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use JonaGoldman\Auth\AuthConfig;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTokens
{
    public function token(): ?HasOne
    {
        return $this->tokens()->one()->latestOfMany();
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(app(AuthConfig::class)->tokenModel);
    }
}
