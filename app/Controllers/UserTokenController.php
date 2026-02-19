<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Token;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Gate;

final readonly class UserTokenController
{
    public function index(User $user): ResourceCollection
    {
        Gate::authorize('list', [Token::class, $user]);

        return $user->tokens()
            ->get()
            ->toResourceCollection();
    }

    public function store(User $user, Request $request): JsonResource
    {
        Gate::authorize('create', [Token::class, $user]);

        return $user->createToken()
            ->toResource();
    }

    public function show(User $user, Token $token): JsonResource
    {
        Gate::authorize('view', [Token::class, $user, $token]);

        return $token->toResource();
    }

    public function destroy(User $user, Token $token): JsonResource
    {
        Gate::authorize('delete', [Token::class, $user, $token]);

        $token->delete();

        return $user->toResource();
    }
}
