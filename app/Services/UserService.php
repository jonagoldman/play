<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;

final class UserService
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createUser(array $attributes, bool $dispatch = false): User
    {
        $validated = Validator::validate($attributes, [
            'name' => ['required', 'string', 'min:2', 'max:190'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:190', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
        ]);

        $user = User::query()->create($validated);

        if ($dispatch) {
            event(new Registered($user));
        }

        return $user;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateUser(User $user, array $attributes): User
    {
        $validated = Validator::validate($attributes, [
            'name' => ['string', 'min:2', 'max:190'],
            'email' => ['string', 'lowercase', 'email', 'max:190', 'unique:'.User::class],
            'password' => [Rules\Password::defaults()],
        ]);

        $user->update($validated);

        return $user;
    }
}
