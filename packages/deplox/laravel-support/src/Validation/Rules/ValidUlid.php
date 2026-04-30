<?php

declare(strict_types=1);

namespace Deplox\Support\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Strict ULID format validation: 26-character Crockford base32.
 *
 * Uses Laravel's Str::isUlid() helper so the canonical implementation stays
 * authoritative. Pair with ULID primary keys to validate route parameters
 * before they hit Eloquent's findOrFail.
 */
final class ValidUlid implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Str::isUlid($value)) {
            $fail(__('support::validation.valid_ulid', ['attribute' => $attribute]));
        }
    }
}
