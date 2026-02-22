<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;

test('session authenticated user takes priority over bearer token', function (): void {
    $sessionUser = User::factory()->create();
    $tokenUser = User::factory()->create();
    $token = Token::factory()->for($tokenUser, 'owner')->create();

    $response = $this->actingAs($sessionUser, 'session')
        ->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test');

    $response->assertSuccessful()
        ->assertJsonPath('id', $sessionUser->id);
});

test('session authenticated user has token relation set to null', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'session')
        ->getJson('/auth-test')
        ->assertSuccessful();

    $authenticatedUser = Auth::guard('dynamic')->user();

    expect($authenticatedUser)->not->toBeNull()
        ->and($authenticatedUser->relationLoaded('token'))->toBeTrue()
        ->and($authenticatedUser->token)->toBeNull();
});

test('bearer fallback authenticates when no session user', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});

test('returns null when no session and no token', function (): void {
    $this->getJson('/auth-test')
        ->assertUnauthorized();
});
