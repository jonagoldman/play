<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Requests\LoginRequest;
use App\Requests\RegisterRequest;
use App\Services\UserService;
use Deplox\Shield\Actions\Login;
use Deplox\Shield\Actions\Logout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final readonly class AuthController
{
    public function __construct(
        private UserService $userService,
        private Login $login,
        private Logout $logout,
    ) {}

    public function register(RegisterRequest $request): JsonResource
    {
        $user = $this->userService->createUser($request->validated(), dispatch: true);

        if ($request->hasSession()) {
            Auth::guard('session')->login($user);

            return $user->toResource();
        }

        $token = $user->createToken(name: 'auth');

        return $user->setRelation('tokens', $user->newCollection([$token]))->toResource();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(LoginRequest $request): JsonResource
    {
        /** @var User $user */
        $user = ($this->login)($request->validated(), stateful: $request->hasSession());

        if (! $request->hasSession()) {
            $token = $user->createToken(name: 'auth');

            return $user->setRelation('tokens', $user->newCollection([$token]))->toResource();
        }

        return $user->toResource();
    }

    public function logout(Request $request): Response
    {
        ($this->logout)($request);

        return response()->noContent();
    }

    public function user(Request $request): JsonResource
    {
        /** @var User $user */
        $user = $request->user();

        return $user->toResource();
    }
}
