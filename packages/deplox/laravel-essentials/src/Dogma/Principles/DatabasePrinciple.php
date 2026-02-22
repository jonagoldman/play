<?php

declare(strict_types=1);

namespace Deplox\Essentials\Dogma\Principles;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Deplox\Essentials\EssentialsConfig;

final class DatabasePrinciple
{
    public static function apply(EssentialsConfig $config): void
    {
        /**
         * Set the default string length for migrations.
         */
        Builder::defaultStringLength($config->defaultStringLength);

        /**
         * Set the default morph key type for migrations.
         */
        Builder::defaultMorphKeyType($config->defaultMorphKeyType);

        /**
         * Blocks potentially destructive Artisan commands in production (e.g., `migrate:fresh`).
         * Prevents accidental data loss and adds a safety net in sensitive environments.
         */
        DB::prohibitDestructiveCommands($config->prohibitDestructiveCommands && app()->isProduction());
    }

    public static function status(): array
    {
        return [
            'defaultStringLength' => Builder::$defaultStringLength,
            'defaultMorphKeyType' => Builder::$defaultMorphKeyType,
            'prohibitsDestructiveCommands' => null, // todo: expose setting
        ];
    }
}
