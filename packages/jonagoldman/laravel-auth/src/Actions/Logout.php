<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class Logout
{
    /**
     * Revoke the current bearer token or invalidate the session.
     */
    public function __invoke(Request $request): void
    {
        /** @var Model $user */
        $user = $request->user();

        $token = $user->getRelation('token');

        if ($token instanceof Model) {
            $token->delete();
        } else {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
