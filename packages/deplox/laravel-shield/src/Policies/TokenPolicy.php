<?php

declare(strict_types=1);

namespace Deplox\Shield\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class TokenPolicy
{
    public function list(Authenticatable&Model $auth, Authenticatable&Model $user): bool
    {
        return $auth->is($user);
    }

    public function view(Authenticatable&Model $auth, Authenticatable&Model $user, Model $token): bool
    {
        return $auth->is($user);
    }

    public function create(Authenticatable&Model $auth, Authenticatable&Model $user): bool
    {
        return $auth->is($user);
    }

    public function delete(Authenticatable&Model $auth, Authenticatable&Model $user, Model $token): bool
    {
        return $auth->is($user);
    }
}
