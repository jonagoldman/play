<?php

declare(strict_types=1);

namespace Deplox\Shield\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Deplox\Shield\Shield;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasMorphTokens
{
    use CreatesTokens;

    public function token(): MorphOne
    {
        return $this->tokens()->one()->latestOfMany();
    }

    public function tokens(): MorphMany
    {
        return $this->morphMany(app(Shield::class)->tokenModel, 'owner');
    }
}
