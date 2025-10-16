<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Dogma\Principles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use JonaGoldman\Essentials\EssentialsConfig;

final class ModelPrinciple
{
    public static function apply(EssentialsConfig $config): void
    {
        /**
         * Disables Laravel's mass assignment protection globally (opt-in).
         * Useful in trusted or local environments where you want to skip defining `$fillable`.
         */
        Model::unguard($config->unguardModel);

        /**
         * Improves how Eloquent handles undefined attributes, lazy loading, and invalid assignments.
         *
         * - Accessing a missing attribute throws an error.
         * - Lazy loading is blocked unless explicitly allowed.
         * - Setting undefined attributes throws instead of failing silently.
         *
         * Avoids subtle bugs and makes model behavior easier to reason about.
         */
        Model::shouldBeStrict($config->strictModel);

        /**
         * Enables automatic eager loading of relationships when models are retrieved.
         * Reduces the N+1 query problem by loading related models upfront.
         */
        Model::automaticallyEagerLoadRelationships($config->automaticEagerLoadRelationships);

        /**
         * Enforces the use of a morph map for all polymorphic relationships.
         * Prevents accidental exposure of class names in the database and improves security.
         */
        Relation::requireMorphMap($config->requireMorphMap);
    }

    public static function status(): array
    {
        return [
            'unguarded' => Model::isUnguarded(),
            'preventsLazyLoading' => Model::preventsLazyLoading(),
            'preventsSilentlyDiscardingAttributes' => Model::preventsSilentlyDiscardingAttributes(),
            'preventsAccessingMissingAttributes' => Model::preventsAccessingMissingAttributes(),
            'automaticallyEagerLoadRelationships' => Model::isAutomaticallyEagerLoadingRelationships(),
        ];
    }
}
