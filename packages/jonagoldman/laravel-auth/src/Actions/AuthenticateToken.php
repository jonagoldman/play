<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use JonaGoldman\Auth\AuthService;
use JonaGoldman\Support\Concerns\Actionable;
use JonaGoldman\Support\Concerns\HasDispatcher;

use function hash;
use function hash_equals;
use function str_contains;

final class AuthenticateToken
{
    use Actionable;
    use HasDispatcher;

    public function execute(string $token): ?Authenticatable
    {
        $accessToken = $this->findToken($token);

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

    private function findToken(string $token): ?Model
    {
        $query = AuthService::tokenModel()::query();

        if (! str_contains($token, '|')) {
            return $query->where('token', hash('sha256', $token))->first();
        }

        [$id, $secret] = explode('|', $token, 2);

        $instance = $query->find($id);

        if ($instance && hash_equals($instance->token, hash('sha256', $secret))) {
            return $instance;
        }

        return null;
    }
}
