<?php

declare(strict_types=1);

use App\Models\Token;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('api login returns user with token', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'tokens'],
        ]);

    $this->assertDatabaseHas('tokens', ['user_id' => $user->id]);
});

test('api login rejects bad credentials', function (): void {
    User::factory()->create(['password' => 'password123']);

    $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('api login rejects unverified user', function (): void {
    $user = User::factory()->unverified()->create(['password' => 'password123']);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('api login validates required fields', function (): void {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

test('spa login returns user without token', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withHeaders(['Origin' => 'https://play.ddev.site'])
        ->get('/auth/csrf-cookie');

    $response = $this->withHeaders(['Origin' => 'https://play.ddev.site'])
        ->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonMissing(['tokens']);

    $this->assertDatabaseMissing('tokens', ['user_id' => $user->id]);
});

test('spa login establishes session for subsequent requests', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withHeaders(['Origin' => 'https://play.ddev.site'])
        ->get('/auth/csrf-cookie');

    $this->withHeaders(['Origin' => 'https://play.ddev.site'])
        ->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertSuccessful();

    $this->withHeaders(['Origin' => 'https://play.ddev.site'])
        ->getJson('/api/user')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id);
});

test('api register returns user with token', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Test User')
        ->assertJsonPath('data.email', 'test@example.com')
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'tokens'],
        ]);
});

test('spa register returns user without token', function (): void {
    Notification::fake();

    $this->withHeaders(['Origin' => 'https://play.ddev.site'])
        ->get('/auth/csrf-cookie');

    $response = $this->withHeaders(['Origin' => 'https://play.ddev.site'])
        ->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Test User')
        ->assertJsonMissing(['tokens']);

    $this->assertDatabaseMissing('tokens', ['user_id' => User::query()->where('email', 'test@example.com')->value('id')]);
});

test('logout revokes bearer token', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    $this->withToken($token->plain, 'Bearer')
        ->postJson('/api/logout')
        ->assertNoContent();

    $this->assertDatabaseMissing('tokens', ['id' => $token->getKey()]);
});

test('authenticated user endpoint returns current user', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/api/user')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

test('unauthenticated user endpoint returns 401', function (): void {
    $this->getJson('/api/user')
        ->assertUnauthorized();
});
