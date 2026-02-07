<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Actions;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use JonaGoldman\Auth\AuthConfig;

final class AuthenticateToken
{
    public function __construct(
        private AuthConfig $config,
        private DispatcherContract $dispatcher,
    ) {}

    public function __invoke(string $token): ?Authenticatable
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $tokenModel */
        $tokenModel = $this->config->tokenModel;

        $accessToken = $tokenModel::findByToken($token);

        if (! $accessToken) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        /** @var Authenticatable|null $user */
        $user = $accessToken->user;

        if ($user) {
            $accessToken->touchLastUsedAt();

            $user->setRelation('token', $accessToken->withoutRelations());

            $this->dispatcher->dispatch(new Authenticated('dynamic', $user));
        }

        return $user;
    }
}
