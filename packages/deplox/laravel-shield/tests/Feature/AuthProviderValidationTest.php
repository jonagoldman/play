<?php

declare(strict_types=1);

use Deplox\Shield\Actions\AuthenticateToken;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\AlternateUser;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

test('token authentication fails when user model does not match configured provider', function (): void {
    Event::fake([Failed::class]);

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $action = new AuthenticateToken(
        shield: new Shield(
            tokenModel: Token::class,
            userModel: AlternateUser::class,
        ),
        dispatcher: Event::getFacadeRoot(),
        request: Request::create('/'),
    );

    $result = $action($token->plain);

    expect($result)->toBeNull();

    Event::assertDispatched(Failed::class, fn (Failed $e) => $e->guard === 'dynamic');
});

test('auth config rejects token model not implementing the contract', function (): void {
    expect(fn () => new Shield(
        tokenModel: Model::class,
        userModel: User::class,
    ))->toThrow(InvalidArgumentException::class, 'must implement the IsAuthToken contract');
});

test('shield rejects nonexistent token model', function (): void {
    expect(fn () => new Shield(
        tokenModel: 'App\\Models\\NonexistentToken',
        userModel: User::class,
    ))->toThrow(InvalidArgumentException::class, 'does not exist');
});

test('shield rejects nonexistent user model', function (): void {
    expect(fn () => new Shield(
        tokenModel: Token::class,
        userModel: 'App\\Models\\NonexistentUser',
    ))->toThrow(InvalidArgumentException::class, 'does not exist');
});

test('shield rejects user model not implementing OwnsTokens', function (): void {
    expect(fn () => new Shield(
        tokenModel: Token::class,
        userModel: Illuminate\Foundation\Auth\User::class,
    ))->toThrow(InvalidArgumentException::class, 'must implement the OwnsTokens contract');
});

test('shield rejects negative default token expiration', function (): void {
    expect(fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        defaultTokenExpiration: -1,
    ))->toThrow(InvalidArgumentException::class, 'non-negative');
});

test('shield accepts zero default token expiration', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        defaultTokenExpiration: 0,
    );

    expect($shield->defaultTokenExpiration)->toBe(0);
});

test('shield accepts null default token expiration', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        defaultTokenExpiration: null,
    );

    expect($shield->defaultTokenExpiration)->toBeNull();
});

test('shield rejects zero last-used-at debounce', function (): void {
    expect(fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        lastUsedAtDebounce: 0,
    ))->toThrow(InvalidArgumentException::class, 'at least 1 second');
});

test('shield rejects negative last-used-at debounce', function (): void {
    expect(fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        lastUsedAtDebounce: -5,
    ))->toThrow(InvalidArgumentException::class, 'at least 1 second');
});

test('token authentication succeeds when user model matches configured provider', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $action = new AuthenticateToken(
        shield: new Shield(
            tokenModel: Token::class,
            userModel: User::class,
        ),
        dispatcher: Event::getFacadeRoot(),
        request: Request::create('/'),
    );

    $result = $action($token->plain);

    expect($result)->not->toBeNull()
        ->and($result->getKey())->toBe($user->getKey());
});
