<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Dogma\Principles;

use Illuminate\Database\Eloquent\Model;
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
         * Automatically eager loads relationships defined in the model's `$with` property.
         * Reduces N+1 query issues and improves performance without needing `with()` everywhere.
         */
        Model::automaticallyEagerLoadRelationships($config->automaticEagerLoadRelationships);
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
