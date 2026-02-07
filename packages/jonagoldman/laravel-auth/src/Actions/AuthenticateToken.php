<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use JonaGoldman\Auth\AuthService;
use JonaGoldman\Support\Concerns\Actionable;
use JonaGoldman\Support\Concerns\HasDispatcher;

final class AuthenticateToken
{
    use Actionable;
    use HasDispatcher;

    public function execute(string $token): ?Authenticatable
    {
        $accessToken = AuthService::findToken($token);

        if (! $accessToken) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        /** @var Authenticatable|null $user */
        $user = $accessToken->user;

        if ($user) {
            $user->setRelation('token', $accessToken->withoutRelations());

            $this->dispatch(new \Illuminate\Auth\Events\Authenticated('dynamic', $user));
        }

        return $user;
    }
}
