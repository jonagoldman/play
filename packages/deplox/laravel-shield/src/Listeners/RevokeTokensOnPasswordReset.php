<?php

declare(strict_types=1);

namespace Deplox\Shield\Listeners;

use Deplox\Shield\Contracts\IsAuthToken;
use Deplox\Shield\Enums\RevokeOnPasswordChange;
use Deplox\Shield\Enums\TokenRevocationReason;
use Deplox\Shield\Enums\TokenType;
use Deplox\Shield\Events\TokenRevoked;
use Deplox\Shield\Shield;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens for password reset events and revokes the user's tokens
 * according to Shield's revokeOnPasswordChange configuration.
 */
final readonly class RevokeTokensOnPasswordReset
{
    public function __construct(
        private Shield $shield,
        private DispatcherContract $dispatcher,
    ) {}

    public function handle(PasswordReset $event): void
    {
        $mode = $this->shield->revokeOnPasswordChange;

        if ($mode === RevokeOnPasswordChange::None) {
            return;
        }

        $user = $event->user;

        if (! method_exists($user, 'tokens')) {
            return;
        }

        $query = $user->tokens();

        if ($mode === RevokeOnPasswordChange::Bearer) {
            $query = $query->where('type', TokenType::Bearer);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Model&IsAuthToken> $tokens */
        $tokens = $query->get();

        foreach ($tokens as $token) {
            $this->dispatcher->dispatch(new TokenRevoked($token, $user, TokenRevocationReason::PasswordReset));
        }

        $query->delete();
    }
}
