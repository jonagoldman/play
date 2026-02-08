<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

final readonly class UserController
{
    public function __construct(
        private UserService $userService
    ) {}

    public function index(): ResourceCollection
    {
        return User::query()
            ->withIncluded(
                allowed: ['tokens'],
                allowedCounts: ['tokens'],
            )
            ->get()
            ->toResourceCollection();
    }

    public function store(Request $request): JsonResource
    {
        return $this->userService
            ->createUser($request->all())
            ->toResource();
    }

    public function show(User $user): JsonResource
    {
        return $user
            ->loadIncluded(
                allowed: ['tokens'],
                allowedCounts: ['tokens'],
            )
            ->toResource();
    }

    public function update(Request $request, User $user): JsonResource
    {
        return $this->userService
            ->updateUser($user, $request->all())
            ->toResource();
    }

    public function destroy(User $user): JsonResource
    {
        $user->delete();

        return $user->toResource();
    }
}
