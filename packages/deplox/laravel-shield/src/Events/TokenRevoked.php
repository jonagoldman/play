<?php

declare(strict_types=1);

namespace Deplox\Shield\Events;

use Deplox\Shield\Contracts\IsAuthToken;
use Deplox\Shield\Enums\TokenRevocationReason;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched when a token is intentionally revoked.
 *
 * Useful for audit logging downstream. Distinct from natural expiration: this event
 * fires only when the application chooses to invalidate the token before its expiry.
 */
final class TokenRevoked
{
    public function __construct(
        public readonly Model&IsAuthToken $token,
        public readonly Authenticatable $user,
        public readonly TokenRevocationReason $reason,
    ) {}
}
