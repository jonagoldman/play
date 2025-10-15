<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Dogma\Principles;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rules\Password;
use JonaGoldman\Essentials\EssentialsConfig;

final class GeneralPrinciple
{
    public static function apply(EssentialsConfig $config): void
    {
        /**
         * Uses `CarbonImmutable` instead of mutable date objects across your app.
         * Prevents unexpected date mutations and improves predictability.
         */
        if ($config->immutableDates) {
            Date::use(CarbonImmutable::class);
        }

        if ($config->setDefaultPasswords) {
            Password::defaults(function () use ($config): Password {
                $rule = Password::min(8)->max($config->defaultStringLength);

                return app()->isProduction()
                    ? $rule->mixedCase()->uncompromised()
                    : $rule;
            });
        }
    }

    public static function status(): array
    {
        return [
            'immutableDates' => ! Date::isMutable(),
            'defaultPasswordRules' => is_callable(Password::$defaultCallback),
        ];
    }
}
