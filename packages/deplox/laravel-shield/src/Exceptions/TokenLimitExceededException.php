<?php

declare(strict_types=1);

namespace Deplox\Shield\Exceptions;

use Illuminate\Auth\AuthenticationException;

/**
 * Thrown when a user attempts to create a token while at the configured per-user token limit.
 *
 * Extends AuthenticationException so existing exception handlers (HTTP 401 mapping,
 * JSON rendering for `api/*` routes) continue to work without changes.
 */
final class TokenLimitExceededException extends AuthenticationException
{
    public static function forUser(int $limit): self
    {
        return new self(__('Maximum number of tokens reached (:limit).', ['limit' => $limit]));
    }
}
