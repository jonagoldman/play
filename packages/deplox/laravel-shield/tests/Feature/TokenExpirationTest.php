<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;

test('createToken sets a default expiration', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken();

    expect($token->expires_at)->not->toBeNull()
        ->and($token->expired)->toBeFalse();
});

test('expired tokens are deleted on authentication attempt', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create([
        'expires_at' => Date::now()->subDay(),
    ]);

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertUnauthorized();

    $this->assertDatabaseMissing('tokens', ['id' => $token->getKey()]);
});

test('expired tokens return 401', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create([
        'expires_at' => Date::now()->subMinute(),
    ]);

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertUnauthorized();
});

test('non-expired tokens authenticate successfully', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create([
        'expires_at' => Date::now()->addDay(),
    ]);

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful();
});

test('unverified users cannot authenticate via token', function (): void {
    $user = User::factory()->unverified()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertUnauthorized();
});
