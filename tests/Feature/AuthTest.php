<?php

declare(strict_types=1);

use App\Models\Token;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('register creates a user and returns a token', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'John Doe')
        ->assertJsonPath('data.email', 'john@example.com')
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'tokens' => [['id', 'name', 'token']],
            ],
        ]);

    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);

    $token = Token::query()->first();
    expect($token->expires_at)->not->toBeNull()
        ->and($token->name)->toBe('auth');
});

test('register validates required fields', function (): void {
    $response = $this->postJson('/api/register', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('register rejects duplicate email', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('login authenticates and returns a token', function (): void {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.email', 'john@example.com')
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'tokens' => [['id', 'name', 'token']],
            ],
        ]);
});

test('login rejects bad credentials', function (): void {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('login rejects nonexistent user', function (): void {
    $response = $this->postJson('/api/login', [
        'email' => 'nobody@example.com',
        'password' => 'password123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('logout revokes the bearer token', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    $response = $this->withToken($token->plain, 'Bearer')
        ->postJson('/api/logout');

    $response->assertNoContent();
    $this->assertDatabaseMissing('tokens', ['id' => $token->getKey()]);
});

test('logout returns 401 when unauthenticated', function (): void {
    $response = $this->postJson('/api/logout');

    $response->assertUnauthorized();
});

test('user returns the authenticated user', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    $response = $this->withToken($token->plain, 'Bearer')
        ->getJson('/api/user');

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

test('user returns 401 when unauthenticated', function (): void {
    $response = $this->getJson('/api/user');

    $response->assertUnauthorized();
});
