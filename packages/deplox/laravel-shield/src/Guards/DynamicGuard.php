<?php

declare(strict_types=1);

namespace Deplox\Shield\Guards;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Http\Request;
use Deplox\Shield\Actions\AuthenticateToken;
use Deplox\Shield\Shield;

final class DynamicGuard
{
    /**
     * @param  \Illuminate\Auth\AuthManager  $auth
     */
    public function __construct(
        private Auth $auth,
        private Shield $shield,
        private AuthenticateToken $authenticateToken,
        private DispatcherContract $dispatcher,
    ) {}

    /**
     * Get the authenticated user for the given request.
     */
    public function __invoke(Request $request): ?User
    {
        foreach ($this->shield->guards as $guard) {
            if ($user = $this->auth->guard($guard)->user()) {
                /** @var \Illuminate\Database\Eloquent\Model&User $user */
                $user->setRelation('token', null);

                $this->dispatcher->dispatch(new Login('dynamic', $user, false));

                return $user;
            }
        }

        $token = ($this->shield->extractToken)($request);

        if ($token) {
            $user = ($this->authenticateToken)($token);

            if ($user) {
                $this->dispatcher->dispatch(new Login('dynamic', $user, false));
            }

            return $user;
        }

        return null;
    }
}
