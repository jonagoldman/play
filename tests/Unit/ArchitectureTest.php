<?php

declare(strict_types=1);

/**
 * It avoids the usage of die, var_dump, and similar functions, and ensures you are not using deprecated PHP functions.
 */
arch()->preset()->php()
    ->ignoring('Deplox\Support\Database\Eloquent\Concerns\HasChildren');

/**
 * It ensures you are not using code that could lead to security vulnerabilities.
 *
 * VerifyEmail uses sha1(email) intentionally to match Laravel's default
 * VerifyEmail notification (Illuminate\Auth\Notifications\VerifyEmail), which
 * is the framework's stable hash format for email verification URLs. Changing
 * the algorithm would break compatibility with notifications generated outside
 * the package.
 */
arch()->preset()->security()
    ->ignoring('Deplox\Shield\Actions\VerifyEmail');

/**
 * It ensures the project structure is following well-known Laravel conventions.
 */
arch()->preset()->laravel()
    ->ignoring('App\Controllers')
    ->ignoring('App\Models\Builders')
    ->ignoring('App\Requests');

/**
 * It ensures you are using strict types in all your files, that all your classes are final, and more.
 */
// arch()->preset()->strict();
