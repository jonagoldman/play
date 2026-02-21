<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use JonaGoldman\Auth\Actions\AuthenticateToken;
use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\Tests\Fixtures\AlternateUser;
use JonaGoldman\Auth\Tests\Fixtures\Token;
use JonaGoldman\Auth\Tests\Fixtures\User;

test('token authentication fails when user model does not match configured provider', function (): void {
    Event::fake([Failed::class]);

    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    $action = new AuthenticateToken(
        config: new AuthConfig(
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
    expect(fn () => new AuthConfig(
        tokenModel: Model::class,
        userModel: User::class,
    ))->toThrow(InvalidArgumentException::class, 'must implement the IsAuthToken contract');
});

test('token authentication succeeds when user model matches configured provider', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    $action = new AuthenticateToken(
        config: new AuthConfig(
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
