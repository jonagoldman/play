<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use JonaGoldman\Auth\Events\TokenAuthenticated;
use JonaGoldman\Auth\Tests\Fixtures\Token;
use JonaGoldman\Auth\Tests\Fixtures\User;

test('token authentication dispatches Attempting and TokenAuthenticated events', function (): void {
    Event::fake([Attempting::class, TokenAuthenticated::class, Login::class]);

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful();

    Event::assertDispatched(Attempting::class, fn (Attempting $e) => $e->guard === 'dynamic');
    Event::assertDispatched(TokenAuthenticated::class);
    Event::assertDispatched(Login::class, fn (Login $e) => $e->guard === 'dynamic');
});

test('failed token authentication dispatches Failed event', function (): void {
    Event::fake([Attempting::class, Failed::class]);

    $this->withToken('invalid-token', 'Bearer')
        ->getJson('/auth-test')
        ->assertUnauthorized();

    Event::assertDispatched(Attempting::class);
    Event::assertDispatched(Failed::class, fn (Failed $e) => $e->guard === 'dynamic');
});

test('expired token dispatches Failed event', function (): void {
    Event::fake([Attempting::class, Failed::class]);

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create([
        'expires_at' => Date::now()->subDay(),
    ]);

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertUnauthorized();

    Event::assertDispatched(Failed::class);
});

test('token authentication dispatches Login event', function (): void {
    Event::fake([Login::class, Attempting::class, TokenAuthenticated::class]);

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful();

    Event::assertDispatched(Login::class, fn (Login $e) => $e->guard === 'dynamic');
});
