<?php

declare(strict_types=1);

use App\Models\Token;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->token = Token::factory()->for($this->user)->create();

    $this->actingAs($this->user, 'dynamic');
});

test('index without include param does not include tokens', function (): void {
    $response = $this->getJson('/api/users');

    $response->assertSuccessful();
    $response->assertJsonMissing(['tokens']);
});

test('index with include=tokens includes tokens', function (): void {
    $response = $this->getJson('/api/users?include=tokens');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [['id', 'tokens']],
        ]);
});

test('index with non-whitelisted include silently ignores it', function (): void {
    $response = $this->getJson('/api/users?include=nonexistent');

    $response->assertSuccessful();
    $response->assertJsonMissing(['nonexistent']);
});

test('index with mixed valid and invalid includes only loads valid ones', function (): void {
    $response = $this->getJson('/api/users?include=tokens,nonexistent');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [['id', 'tokens']],
        ]);
});

test('show without include param does not include tokens', function (): void {
    $response = $this->getJson("/api/users/{$this->user->id}");

    $response->assertSuccessful();
    $response->assertJsonMissing(['tokens']);
});

test('show with include=tokens loads tokens', function (): void {
    $response = $this->getJson("/api/users/{$this->user->id}?include=tokens");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => ['id', 'tokens'],
        ]);
});

test('empty include param does not load any relationships', function (): void {
    $response = $this->getJson('/api/users?include=');

    $response->assertSuccessful();
    $response->assertJsonMissing(['tokens']);
});

test('index with with_count=tokens includes tokens count', function (): void {
    $response = $this->getJson('/api/users?with_count=tokens');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.tokens_count', 1);
});

test('show with with_count=tokens includes tokens count', function (): void {
    $response = $this->getJson("/api/users/{$this->user->id}?with_count=tokens");

    $response->assertSuccessful()
        ->assertJsonPath('data.tokens_count', 1);
});

test('with_count with non-whitelisted value silently ignores it', function (): void {
    $response = $this->getJson('/api/users?with_count=nonexistent');

    $response->assertSuccessful();
    $response->assertJsonMissing(['nonexistent_count']);
});

test('include and with_count can be combined', function (): void {
    $response = $this->getJson('/api/users?include=tokens&with_count=tokens');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [['id', 'tokens', 'tokens_count']],
        ])
        ->assertJsonPath('data.0.tokens_count', 1);
});
