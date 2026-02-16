<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Guards;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
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
        private AuthenticateToken $authenticateToken,
        private DispatcherContract $dispatcher,
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

                $this->dispatcher->dispatch(new Login('dynamic', $user, false));

                return $user;
            }
        }

        if ($token = $request->bearerToken()) {
            $user = ($this->authenticateToken)($token);

            if ($user) {
                $this->dispatcher->dispatch(new Login('dynamic', $user, false));
            }

            return $user;
        }

        return null;
    }
}
