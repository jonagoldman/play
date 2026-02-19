<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Requests\LoginRequest;
use App\Requests\RegisterRequest;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use JonaGoldman\Auth\Actions\Login;
use JonaGoldman\Auth\Actions\Logout;

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

        $token = $user->createToken(name: 'auth');

        return $user->setRelation('tokens', $user->newCollection([$token]))->toResource();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(LoginRequest $request): JsonResource
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $request->validated('email'))->first();

        /** @var string $password */
        $password = $request->validated('password');

        $token = ($this->login)($user, $password, tokenName: 'auth');

        /** @var User $user */
        return $user->setRelation('tokens', $user->newCollection([$token]))->toResource();
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
