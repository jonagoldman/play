<?php

declare(strict_types=1);

use Deplox\Shield\Actions\Logout;
use Deplox\Shield\Enums\TokenRevocationReason;
use Deplox\Shield\Events\TokenRevoked;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

test('Logout::all deletes every token belonging to the user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Token::factory()->for($user, 'owner')->create();
    Token::factory()->for($user, 'owner')->create();
    Token::factory()->for($user, 'owner')->create();

    $otherToken = Token::factory()->for($other, 'owner')->create();

    $deleted = app(Logout::class)->all($user);

    expect($deleted)->toBe(3)
        ->and($user->tokens()->count())->toBe(0)
        ->and(Token::query()->where($otherToken->getKeyName(), $otherToken->getKey())->exists())->toBeTrue();
});

test('Logout::all dispatches TokenRevoked once per token with the given reason', function (): void {
    Event::fake([TokenRevoked::class]);

    $user = User::factory()->create();
    Token::factory()->for($user, 'owner')->create();
    Token::factory()->for($user, 'owner')->create();

    app(Logout::class)->all($user, reason: TokenRevocationReason::PasswordReset);

    Event::assertDispatched(TokenRevoked::class, 2);
    Event::assertDispatched(
        TokenRevoked::class,
        fn (TokenRevoked $e): bool => $e->reason === TokenRevocationReason::PasswordReset && $e->user->getKey() === $user->getKey(),
    );
});

test('Logout::all returns 0 when the user has no tokens', function (): void {
    $user = User::factory()->create();

    $deleted = app(Logout::class)->all($user);

    expect($deleted)->toBe(0);
});

test('Logout dispatches TokenRevoked for the current bearer token', function (): void {
    Event::fake([TokenRevoked::class]);

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful();

    app(Logout::class)(request());

    Event::assertDispatched(
        TokenRevoked::class,
        fn (TokenRevoked $e): bool => $e->reason === TokenRevocationReason::Logout && $e->token->getKey() === $token->getKey(),
    );
});
