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
        $query->with($this->parseIncluded('include', $allowed));
        $query->withCount($this->parseIncluded('with_count', $allowedCounts));
    }

    /**
     * Load included relationships and counts on an already-resolved model.
     *
     * @param  list<string>  $allowed
     * @param  list<string>  $allowedCounts
     */
    public function loadIncluded(array $allowed = [], array $allowedCounts = []): static
    {
        $this->loadMissing($this->parseIncluded('include', $allowed));
        $this->loadCount($this->parseIncluded('with_count', $allowedCounts));

        return $this;
    }

    /**
     * Parse a comma-separated query parameter and filter it against an allowlist.
     *
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function parseIncluded(string $param, array $allowed): array
    {
        $value = Request::query($param);

        if (! is_string($value) || $value === '') {
            return [];
        }

        return array_values(array_intersect(
            array_map(mb_trim(...), explode(',', $value)),
            $allowed,
        ));
    }
}
