<?php

declare(strict_types=1);

namespace Deplox\Shield\Events;

/**
 * Dispatched when a stateless or stateful login attempt fails.
 *
 * Distinct from Laravel's Auth\Events\Failed event: this carries only the field
 * (default 'email') and IP address, never the submitted password or token, so
 * it is safe to log directly. Pair with Lockout for anomaly detection.
 */
final class FailedLogin
{
    public function __construct(
        public readonly string $field,
        public readonly string $identifier,
        public readonly ?string $ip,
    ) {}
}
