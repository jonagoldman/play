<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Actions;

use App\Models\Token;
use App\Models\User;
use Illuminate\Support\Str;
use JonaGoldman\Support\Concerns\Actionable;
use JonaGoldman\Support\Concerns\HasDispatcher;

final class AuthenticateToken
{
    use Actionable;
    use HasDispatcher;

    public function execute(string $token): ?User
    {
        /** @var Token|null $token */
        $token = Token::query()->where('token', hash('sha256', Str::after($token, '|')))->first();

        $user = null;

        if ($token) {
            $user = $token->user->setRelation('token', $token->withoutRelations());

            $this->dispatch(new \Illuminate\Auth\Events\Authenticated('dynamic', $user));
        }

        return $user;
    }
}
