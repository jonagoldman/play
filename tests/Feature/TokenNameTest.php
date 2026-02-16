<?php

declare(strict_types=1);

use App\Models\Token;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('tokens can be created with a name', function (): void {
    $user = User::factory()->create();
    $tokenService = app(TokenService::class);

    $token = $tokenService->createToken($user, name: 'Mobile App');

    expect($token->name)->toBe('Mobile App');
    $this->assertDatabaseHas('tokens', [
        'id' => $token->getKey(),
        'name' => 'Mobile App',
    ]);
});

test('token name appears in resource output', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'dynamic')
        ->postJson("/api/users/{$user->id}/tokens")
        ->assertSuccessful()
        ->assertJsonStructure(['data' => ['id', 'name']]);
});

test('register creates tokens with auth name', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSuccessful();

    $token = Token::query()->first();
    expect($token->name)->toBe('auth');
});

test('login creates tokens with auth name', function (): void {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ])->assertSuccessful();

    $token = Token::query()->latest()->first();
    expect($token->name)->toBe('auth');
});

test('tokens can be created without a name', function (): void {
    $user = User::factory()->create();
    $tokenService = app(TokenService::class);

    $token = $tokenService->createToken($user);

    expect($token->name)->toBeNull();
});
