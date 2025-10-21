<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Token;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

final readonly class UserTokenController
{
    public function __construct(
        private TokenService $tokenService
    ) {}

    public function index(User $user): ResourceCollection
    {
        return $user->tokens()
            ->get()
            ->toResourceCollection();
    }

    public function store(User $user, Request $request): JsonResource
    {
        return $this->tokenService
            ->createToken($user)
            ->toResource();
    }

    public function show(User $user, Token $token): JsonResource
    {
        return $token->toResource();
    }

    public function destroy(User $user, Token $token): JsonResource
    {
        $token->delete();

        return $user->toResource();
    }
}
