<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Actions;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Http\Request;
use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\Events\TokenAuthenticated;

final class AuthenticateToken
{
    public function __construct(
        private AuthConfig $config,
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
        /** @var class-string<\Illuminate\Database\Eloquent\Model&\JonaGoldman\Auth\Contracts\IsAuthToken> $tokenModel */
        $tokenModel = $this->config->tokenModel;

        $accessToken = $tokenModel::findByToken($token);

        if (! $accessToken) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();

            return null;
        }

        /** @var Authenticatable|null $user */
        $user = $accessToken->user;

        if (! $user instanceof $this->config->userModel) {
            return null;
        }

        if (! ($this->config->validateToken)($accessToken, $this->request)) {
            $this->dispatcher->dispatch(new Failed('dynamic', $user, ['token' => $token]));

            return null;
        }

        if ($this->config->validateUser && ! ($this->config->validateUser)($user)) {
            $this->dispatcher->dispatch(new Failed('dynamic', $user, ['token' => $token]));

            return null;
        }

        $accessToken->touchLastUsedAt();

        $user->setRelation('token', $accessToken->withoutRelations());

        $this->dispatcher->dispatch(new TokenAuthenticated($accessToken));

        return $user;
    }
}
