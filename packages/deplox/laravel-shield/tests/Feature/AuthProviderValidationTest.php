<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Deplox\Shield\Actions\AuthenticateToken;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\AlternateUser;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;

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
