<?php

declare(strict_types=1);

use Deplox\Shield\Enums\TokenLimitBehavior;
use Deplox\Shield\Exceptions\TokenLimitExceededException;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;

function shieldWithLimit(?int $limit, TokenLimitBehavior $behavior = TokenLimitBehavior::Reject): void
{
    app()->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'dpl_',
        maxTokensPerUser: $limit,
        onTokenLimit: $behavior,
        validateUser: fn ($user): bool => $user->verified_at !== null,
    ));
}

test('createToken does not throw when max tokens per user is null', function (): void {
    shieldWithLimit(null);

    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $user->createToken();
    }

    expect($user->tokens()->count())->toBe(5);
});

test('createToken throws TokenLimitExceededException when at the cap with Reject behavior', function (): void {
    shieldWithLimit(2);

    $user = User::factory()->create();
    $user->createToken();
    $user->createToken();

    expect(fn () => $user->createToken())
        ->toThrow(TokenLimitExceededException::class);
});

test('createToken with Reject does not delete existing tokens on failure', function (): void {
    shieldWithLimit(1);

    $user = User::factory()->create();
    $first = $user->createToken();

    try {
        $user->createToken();
    } catch (TokenLimitExceededException) {
        // expected
    }

    expect(Token::query()->where($first->getKeyName(), $first->getKey())->exists())->toBeTrue()
        ->and($user->tokens()->count())->toBe(1);
});

test('createToken with PruneOldest behavior deletes the oldest token to make room', function (): void {
    shieldWithLimit(2, TokenLimitBehavior::PruneOldest);

    $user = User::factory()->create();
    $oldest = $user->createToken(name: 'oldest');
    $this->travel(1)->seconds();
    $middle = $user->createToken(name: 'middle');
    $this->travel(1)->seconds();
    $newest = $user->createToken(name: 'newest');

    expect($user->tokens()->count())->toBe(2)
        ->and(Token::query()->where($oldest->getKeyName(), $oldest->getKey())->exists())->toBeFalse()
        ->and(Token::query()->where($middle->getKeyName(), $middle->getKey())->exists())->toBeTrue()
        ->and(Token::query()->where($newest->getKeyName(), $newest->getKey())->exists())->toBeTrue();
});

test('createToken with PruneOldest accepts maxTokensPerUser=1', function (): void {
    shieldWithLimit(1, TokenLimitBehavior::PruneOldest);

    $user = User::factory()->create();
    $first = $user->createToken();
    $this->travel(1)->seconds();
    $second = $user->createToken();

    expect($user->tokens()->count())->toBe(1)
        ->and(Token::query()->where($first->getKeyName(), $first->getKey())->exists())->toBeFalse()
        ->and(Token::query()->where($second->getKeyName(), $second->getKey())->exists())->toBeTrue();
});

test('Shield rejects negative maxTokensPerUser', function (): void {
    expect(fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        maxTokensPerUser: 0,
    ))->toThrow(InvalidArgumentException::class);
});

test('TokenLimitExceededException renders as a 401', function (): void {
    $exception = TokenLimitExceededException::forUser(5);

    expect($exception)->toBeInstanceOf(Illuminate\Auth\AuthenticationException::class);
});
