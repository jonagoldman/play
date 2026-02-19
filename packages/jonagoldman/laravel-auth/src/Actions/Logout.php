<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Actions;

use Illuminate\Http\Request;
use JonaGoldman\Auth\TransientToken;

final class Logout
{
    /**
     * Revoke the current bearer token or invalidate the session.
     */
    public function __invoke(Request $request): void
    {
        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $request->user();

        $token = $user->getRelation('token');

        if ($user->relationLoaded('token') && ! $token instanceof TransientToken) {
            /** @var \Illuminate\Database\Eloquent\Model|null $token */
            $token?->delete();
        } else {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
