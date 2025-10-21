<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Token;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        return $this->hasMany(Token::class);
    }
}
