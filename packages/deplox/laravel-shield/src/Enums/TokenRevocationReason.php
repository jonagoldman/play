<?php

declare(strict_types=1);

namespace Deplox\Shield\Enums;

/**
 * Why a token was revoked. Carried on the TokenRevoked event for audit logging.
 */
enum TokenRevocationReason: string
{
    case Logout = 'logout';
    case LogoutAll = 'logout-all';
    case PasswordReset = 'password-reset';
}
