<?php

declare(strict_types=1);

namespace Deplox\Shield\Actions;

use Deplox\Shield\Contracts\IsAuthToken;
use Deplox\Shield\Events\TokenRevoked;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class Logout
{
    public function __construct(
        private ?DispatcherContract $dispatcher = null,
    ) {}

    /**
     * Revoke the current bearer token or invalidate the session.
     */
    public function __invoke(Request $request): void
    {
        /** @var Model&Authenticatable $user */
        $user = $request->user();

        $token = $user->getRelation('token');

        if ($token instanceof Model && $token instanceof IsAuthToken) {
            $this->dispatchRevoked($token, $user, 'logout');
            $token->delete();

            return;
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * Revoke every token belonging to the user.
     *
     * Returns the number of tokens revoked. Dispatches a TokenRevoked event
     * per token so audit listeners can record each revocation.
     */
    public function all(Authenticatable $user, string $reason = 'logout-all'): int
    {
        if (! method_exists($user, 'tokens')) {
            return 0;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Model&IsAuthToken> $tokens */
        $tokens = $user->tokens()->get();

        foreach ($tokens as $token) {
            $this->dispatchRevoked($token, $user, $reason);
        }

        return $user->tokens()->delete();
    }

    private function dispatchRevoked(Model&IsAuthToken $token, Authenticatable $user, string $reason): void
    {
        $this->dispatcher?->dispatch(new TokenRevoked($token, $user, $reason));
    }
}
