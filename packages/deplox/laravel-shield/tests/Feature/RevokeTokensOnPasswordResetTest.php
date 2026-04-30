<?php

declare(strict_types=1);

use Deplox\Shield\Enums\RevokeOnPasswordChange;
use Deplox\Shield\Enums\TokenRevocationReason;
use Deplox\Shield\Enums\TokenType;
use Deplox\Shield\Events\TokenRevoked;
use Deplox\Shield\Listeners\RevokeTokensOnPasswordReset;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;

function shieldWithRevoke(RevokeOnPasswordChange $mode): void
{
    app()->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'dpl_',
        revokeOnPasswordChange: $mode,
        validateUser: fn ($user): bool => $user->verified_at !== null,
    ));
}

test('Bearer mode revokes only Bearer tokens, leaves Remember tokens intact', function (): void {
    shieldWithRevoke(RevokeOnPasswordChange::Bearer);

    $user = User::factory()->create();
    $bearer = Token::factory()->for($user, 'owner')->create(['type' => TokenType::Bearer]);
    $remember = Token::factory()->for($user, 'owner')->create(['type' => TokenType::Remember]);

    app(RevokeTokensOnPasswordReset::class)->handle(new PasswordReset($user));

    expect(Token::query()->where($bearer->getKeyName(), $bearer->getKey())->exists())->toBeFalse()
        ->and(Token::query()->where($remember->getKeyName(), $remember->getKey())->exists())->toBeTrue();
});

test('All mode revokes every token regardless of type', function (): void {
    shieldWithRevoke(RevokeOnPasswordChange::All);

    $user = User::factory()->create();
    Token::factory()->for($user, 'owner')->create(['type' => TokenType::Bearer]);
    Token::factory()->for($user, 'owner')->create(['type' => TokenType::Remember]);

    app(RevokeTokensOnPasswordReset::class)->handle(new PasswordReset($user));

    expect($user->tokens()->count())->toBe(0);
});

test('None mode leaves all tokens intact', function (): void {
    shieldWithRevoke(RevokeOnPasswordChange::None);

    $user = User::factory()->create();
    Token::factory()->for($user, 'owner')->create(['type' => TokenType::Bearer]);
    Token::factory()->for($user, 'owner')->create(['type' => TokenType::Remember]);

    app(RevokeTokensOnPasswordReset::class)->handle(new PasswordReset($user));

    expect($user->tokens()->count())->toBe(2);
});

test('listener dispatches TokenRevoked once per revoked token with reason "password-reset"', function (): void {
    shieldWithRevoke(RevokeOnPasswordChange::All);

    Event::fake([TokenRevoked::class]);

    $user = User::factory()->create();
    Token::factory()->for($user, 'owner')->create();
    Token::factory()->for($user, 'owner')->create();

    app(RevokeTokensOnPasswordReset::class)->handle(new PasswordReset($user));

    Event::assertDispatched(TokenRevoked::class, 2);
    Event::assertDispatched(
        TokenRevoked::class,
        fn (TokenRevoked $e): bool => $e->reason === TokenRevocationReason::PasswordReset,
    );
});

test('PasswordReset event triggers token revocation via the registered listener', function (): void {
    shieldWithRevoke(RevokeOnPasswordChange::Bearer);
    app(Shield::class)->boot(app(), app('Illuminate\\Contracts\\Http\\Kernel'), app('auth'));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create(['type' => TokenType::Bearer]);

    event(new PasswordReset($user));

    expect(Token::query()->where($token->getKeyName(), $token->getKey())->exists())->toBeFalse();
});

test('listener does nothing when user model lacks tokens() relationship', function (): void {
    shieldWithRevoke(RevokeOnPasswordChange::All);

    $userWithoutTokens = new class extends Illuminate\Foundation\Auth\User
    {
        public function getKey(): string
        {
            return 'no-tokens';
        }
    };

    expect(fn () => app(RevokeTokensOnPasswordReset::class)
        ->handle(new PasswordReset($userWithoutTokens)))
        ->not->toThrow(Throwable::class);
});
