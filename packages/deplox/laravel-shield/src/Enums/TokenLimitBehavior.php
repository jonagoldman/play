<?php

declare(strict_types=1);

namespace Deplox\Shield\Enums;

/**
 * How CreatesTokens behaves when a user is already at the configured token cap.
 */
enum TokenLimitBehavior: string
{
    case Reject = 'reject';
    case PruneOldest = 'prune-oldest';
}
