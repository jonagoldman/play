<?php

declare(strict_types=1);

namespace JonaGoldman\Support\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait CanIncludeRelationships
{
    /**
     * Scope to eager load relationships and counts requested via query parameters.
     *
     * @param  list<string>  $allowed
     * @param  list<string>  $allowedCounts
     */
    #[Scope]
    public function withIncluded(Builder $query, array $allowed = [], array $allowedCounts = []): void
    {
        $query->with(array_intersect(explode(',', Request::query('include', '')), $allowed));
        $query->withCount(array_intersect(explode(',', Request::query('with_count', '')), $allowedCounts));
    }

    /**
     * Load included relationships and counts on an already-resolved model.
     *
     * @param  list<string>  $allowed
     * @param  list<string>  $allowedCounts
     */
    public function loadIncluded(array $allowed = [], array $allowedCounts = []): static
    {
        $this->loadMissing(array_intersect(explode(',', Request::query('include', '')), $allowed));
        $this->loadCount(array_intersect(explode(',', Request::query('with_count', '')), $allowedCounts));

        return $this;
    }
}
