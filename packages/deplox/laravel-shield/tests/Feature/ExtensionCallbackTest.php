<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Deplox\Shield\Concerns\ActingAsToken;
use Deplox\Shield\Contracts\IsAuthToken;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;

uses(ActingAsToken::class);

test('extractToken extracts token from custom header', function (): void {
    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        extractToken: fn ($request) => $request->header('X-API-Token'),
    ));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withHeader('X-API-Token', $token->plain)
        ->getJson('/auth-test')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});

test('extractToken returning null skips token authentication', function (): void {
    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        extractToken: fn ($request) => null,
    ));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertUnauthorized();
});

test('extractToken extracts token from query param', function (): void {
    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        extractToken: fn ($request) => $request->query('api_token'),
    ));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->getJson('/auth-test?api_token='.$token->plain)
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});

test('validateToken rejects when returning false', function (): void {
    Event::fake([Failed::class]);

    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        validateToken: fn (IsAuthToken $token, $request) => false,
    ));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertUnauthorized();

    Event::assertDispatched(Failed::class, fn (Failed $e) => $e->guard === 'dynamic');
});

test('validateToken allows when returning true', function (): void {
    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        validateToken: fn (IsAuthToken $token, $request) => true,
    ));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});

test('validateToken receives the correct token model and request', function (): void {
    $captured = new stdClass;
    $captured->token = null;
    $captured->request = null;

    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        validateToken: function (IsAuthToken $token, $request) use ($captured): bool {
            $captured->token = $token;
            $captured->request = $request;

            return true;
        },
    ));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful();

    expect($captured->token)->toBeInstanceOf(Token::class)
        ->and($captured->token->getKey())->toBe($token->getKey())
        ->and($captured->request)->not->toBeNull()
        ->and($captured->request->bearerToken())->toBe($token->plain);
});

test('actingAsToken authenticates the user via bearer token', function (): void {
    $user = User::factory()->create();

    $this->actingAsToken($user)
        ->getJson('/auth-test')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});
