<?php

declare(strict_types=1);

namespace Deplox\Shield\Actions;

use Deplox\Shield\Events\TokenAuthenticated;
use Deplox\Shield\Shield;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Http\Request;
use Throwable;

final class AuthenticateToken
{
    public function __construct(
        private Shield $shield,
        private DispatcherContract $dispatcher,
        private Request $request,
    ) {}

    public function __invoke(string $token): ?Authenticatable
    {
        $this->dispatcher->dispatch(new Attempting('dynamic', ['token' => $token], false));

        $user = $this->resolve($token);

        if (! $user) {
            $this->dispatcher->dispatch(new Failed('dynamic', null, ['token' => $token]));
        }

        return $user;
    }

    private function resolve(string $token): ?Authenticatable
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model&\Deplox\Shield\Contracts\IsAuthToken> $tokenModel */
        $tokenModel = $this->shield->tokenModel;

        $accessToken = $tokenModel::findByToken($token);

        if (! $accessToken) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();

            return null;
        }

        /** @var Authenticatable|null $user */
        $user = $accessToken->owner;

        if (! $user instanceof $this->shield->userModel) {
            return null;
        }

        try {
            $validToken = ($this->shield->validateToken)($accessToken, $this->request);
        } catch (Throwable) {
            $this->dispatcher->dispatch(new Failed('dynamic', $user, ['token' => $token]));

            return null;
        }

        if (! $validToken) {
            $this->dispatcher->dispatch(new Failed('dynamic', $user, ['token' => $token]));

            return null;
        }

        try {
            $validUser = ($this->shield->validateUser)($user);
        } catch (Throwable) {
            $this->dispatcher->dispatch(new Failed('dynamic', $user, ['token' => $token]));

            return null;
        }

        if (! $validUser) {
            $this->dispatcher->dispatch(new Failed('dynamic', $user, ['token' => $token]));

            return null;
        }

        $accessToken->touchLastUsedAt();

        $user->setRelation('token', $accessToken->withoutRelations());

        $this->dispatcher->dispatch(new TokenAuthenticated($accessToken));

        return $user;
    }
}
