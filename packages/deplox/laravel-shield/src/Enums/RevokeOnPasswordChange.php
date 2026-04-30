<?php

declare(strict_types=1);

namespace Deplox\Shield\Enums;

/**
 * Which tokens to revoke when a user's password changes.
 *
 * - `All`: revoke every token, including Remember tokens.
 * - `Bearer`: revoke only Bearer tokens (default; Remember tokens persist
 *    so the user's other browsers stay logged in until they re-authenticate
 *    via the Remember cookie).
 * - `None`: do nothing (caller is responsible for revocation if desired).
 */
enum RevokeOnPasswordChange: string
{
    case All = 'all';
    case Bearer = 'bearer';
    case None = 'none';
}
