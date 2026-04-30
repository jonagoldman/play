<?php

declare(strict_types=1);

namespace Deplox\Support\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

/**
 * Add a `?search=` query-param scope to a model.
 *
 * Searches with LIKE across the columns returned by getSearchable(), which
 * defaults to a `$searchable` property on the model. Use the allowlist style
 * to avoid SQL injection or unintended column exposure.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSearch
{
    /**
     * Filter the query by the `search` request parameter against an allowlist of columns.
     *
     * Empty allowlists or empty/missing search terms are no-ops.
     *
     * @param  list<string>  $allowed
     */
    #[Scope]
    public function whereSearch(Builder $query, array $allowed = [], ?string $param = 'search'): void
    {
        if ($allowed === []) {
            return;
        }

        $term = Request::query($param);

        if (! is_string($term) || mb_trim($term) === '') {
            return;
        }

        $columns = array_values(array_intersect($allowed, $this->getSearchable()));

        if ($columns === []) {
            return;
        }

        $like = '%'.mb_trim($term).'%';

        $query->where(function (Builder $query) use ($columns, $like): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', $like);
            }
        });
    }

    /**
     * Get the allowlist of searchable columns for this model.
     *
     * Override the method (or declare a `$searchable` property) to customize.
     * Implemented as a method to avoid PHP 8.4 trait property conflicts with
     * final classes that may redefine the same name.
     *
     * @return list<string>
     */
    public function getSearchable(): array
    {
        return $this->searchable ?? [];
    }
}
