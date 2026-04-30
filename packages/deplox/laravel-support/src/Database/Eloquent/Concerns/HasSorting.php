<?php

declare(strict_types=1);

namespace Deplox\Support\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

/**
 * Add a `?sort=` query-param scope to a model.
 *
 * Accepts a comma-separated list of columns prefixed with `-` for descending,
 * e.g. `?sort=-created_at,name`. Filters against an allowlist; invalid columns
 * are silently dropped.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSorting
{
    /**
     * @param  list<string>  $allowed
     */
    #[Scope]
    public function withSorting(Builder $query, array $allowed = [], ?string $param = 'sort'): void
    {
        if ($allowed === []) {
            return;
        }

        $value = Request::query($param);

        if (! is_string($value) || $value === '') {
            return;
        }

        foreach (explode(',', $value) as $part) {
            $part = mb_trim($part);

            if ($part === '') {
                continue;
            }

            $direction = str_starts_with($part, '-') ? 'desc' : 'asc';

            if (str_starts_with($part, '-') || str_starts_with($part, '+')) {
                $part = mb_substr($part, 1);
            }

            if (! in_array($part, $allowed, true)) {
                continue;
            }

            $query->orderBy($part, $direction);
        }
    }
}
