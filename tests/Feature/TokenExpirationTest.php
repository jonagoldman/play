<?php

declare(strict_types=1);

use App\Models\Token;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

test('tokens get a default expiration when none provided', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'dynamic')
        ->postJson("/api/users/{$user->id}/tokens")
        ->assertSuccessful()
        ->assertJsonPath('data.expired', false);

    $token = Token::query()->where('user_id', $user->id)->latest()->first();
    expect($token->expires_at)->not->toBeNull();
});

test('expired tokens are deleted on authentication attempt', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create([
        'expires_at' => Date::now()->subDay(),
    ]);

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/api/user')
        ->assertUnauthorized();

    $this->assertDatabaseMissing('tokens', ['id' => $token->getKey()]);
});

test('expired tokens return 401', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create([
        'expires_at' => Date::now()->subMinute(),
    ]);

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/api/user')
        ->assertUnauthorized();
});

test('non-expired tokens authenticate successfully', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create([
        'expires_at' => Date::now()->addDay(),
    ]);

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/api/user')
        ->assertSuccessful();
});

test('unverified users cannot authenticate via token', function (): void {
    $user = User::factory()->unverified()->create();
    $token = Token::factory()->for($user)->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/api/user')
        ->assertUnauthorized();
});
