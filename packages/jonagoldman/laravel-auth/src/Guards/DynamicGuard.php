<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Guards;

use App\Auth\Actions\AuthenticateToken;
use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;

final class DynamicGuard
{
    /**
     * @param  \Illuminate\Auth\AuthManager  $auth
     */
    public function __construct(
        private Auth $auth,
    ) {}

    /**
     * Get the authenticated user for the given request.
     */
    public function user(Request $request): ?User
    {
        if ($user = $this->auth->guard('session')->user()) {
            return $user;
        }

        if ($token = $request->bearerToken()) {
            return AuthenticateToken::run($token);
        }

        return null;
    }
}
