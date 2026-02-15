<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Guards;

use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use JonaGoldman\Auth\Actions\AuthenticateToken;
use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\TransientToken;

final class DynamicGuard
{
    /**
     * @param  \Illuminate\Auth\AuthManager  $auth
     */
    public function __construct(
        private Auth $auth,
        private AuthConfig $config,
    ) {}

    /**
     * Get the authenticated user for the given request.
     */
    public function __invoke(Request $request): ?User
    {
        foreach ($this->config->guards as $guard) {
            if ($user = $this->auth->guard($guard)->user()) {
                /** @var \Illuminate\Database\Eloquent\Model&User $user */
                $user->setRelation('token', new TransientToken);

                return $user;
            }
        }

        if ($token = $request->bearerToken()) {
            return app(AuthenticateToken::class)($token);
        }

        return null;
    }
}
