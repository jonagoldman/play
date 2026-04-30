<?php

declare(strict_types=1);

namespace Deplox\Support\Validation\Rules;

use Illuminate\Validation\Rules\Password;

/**
 * Pre-configured Password rule presets.
 *
 * Returns Laravel's stock Password rule with our policy baked in. Use the
 * static factories to scope by risk level. The returned object is itself a
 * validation rule, so it slots straight into `['password' => [StrongPassword::default()]]`.
 */
final class StrongPassword
{
    /**
     * 12+ chars, mixed case, numbers, symbols, and the haveibeenpwned check.
     */
    public static function default(): Password
    {
        return Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }

    /**
     * 8+ chars with letters and numbers — appropriate for low-risk contexts.
     */
    public static function moderate(): Password
    {
        return Password::min(8)
            ->letters()
            ->numbers();
    }
}
