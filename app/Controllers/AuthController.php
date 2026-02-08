<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Requests\LoginRequest;
use App\Requests\RegisterRequest;
use App\Services\TokenService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class AuthController
{
    public function __construct(
        private UserService $userService,
        private TokenService $tokenService,
    ) {}

    public function register(RegisterRequest $request): JsonResource
    {
        $user = $this->userService->createUser($request->validated(), dispatch: true);

        $token = $this->tokenService->createToken($user);

        return $user->setRelation('tokens', $user->newCollection([$token]))->toResource();
    }

    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResource
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $request->validated('email'))->first();

        /** @var string $password */
        $password = $request->validated('password');

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $this->tokenService->createToken($user);

        return $user->setRelation('tokens', $user->newCollection([$token]))->toResource();
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var \App\Models\Token|null $currentToken */
        $currentToken = $user->relationLoaded('token') ? $user->getRelation('token') : null;

        if ($request->bearerToken() && $currentToken) {
            $currentToken->delete();
        } else {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(status: 204);
    }

    public function user(Request $request): JsonResource
    {
        /** @var User $user */
        $user = $request->user();

        return $user->toResource();
    }
}
