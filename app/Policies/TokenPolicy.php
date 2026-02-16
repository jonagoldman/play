<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Token;
use App\Models\User;

final class TokenPolicy
{
    public function list(User $auth, User $user): bool
    {
        return $auth->is($user);
    }

    public function view(User $auth, User $user, Token $token): bool
    {
        return $auth->is($user);
    }

    public function create(User $auth, User $user): bool
    {
        return $auth->is($user);
    }

    public function delete(User $auth, User $user, Token $token): bool
    {
        return $auth->is($user);
    }
}
