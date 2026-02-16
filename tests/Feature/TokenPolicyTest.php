<?php

declare(strict_types=1);

use App\Models\Token;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->token = Token::factory()->for($this->user)->create();
    $this->otherUser = User::factory()->create();
    $this->otherToken = Token::factory()->for($this->otherUser)->create();
});

test('user can list their own tokens', function (): void {
    $this->actingAs($this->user, 'dynamic')
        ->getJson("/api/users/{$this->user->id}/tokens")
        ->assertSuccessful();
});

test('user cannot list another user tokens', function (): void {
    $this->actingAs($this->user, 'dynamic')
        ->getJson("/api/users/{$this->otherUser->id}/tokens")
        ->assertForbidden();
});

test('user can view their own token', function (): void {
    $this->actingAs($this->user, 'dynamic')
        ->getJson("/api/users/{$this->user->id}/tokens/{$this->token->id}")
        ->assertSuccessful();
});

test('user cannot view another user token', function (): void {
    $this->actingAs($this->user, 'dynamic')
        ->getJson("/api/users/{$this->otherUser->id}/tokens/{$this->otherToken->id}")
        ->assertForbidden();
});

test('user can create a token for themselves', function (): void {
    $this->actingAs($this->user, 'dynamic')
        ->postJson("/api/users/{$this->user->id}/tokens")
        ->assertSuccessful();
});

test('user cannot create a token for another user', function (): void {
    $this->actingAs($this->user, 'dynamic')
        ->postJson("/api/users/{$this->otherUser->id}/tokens")
        ->assertForbidden();
});

test('user can delete their own token', function (): void {
    $this->actingAs($this->user, 'dynamic')
        ->deleteJson("/api/users/{$this->user->id}/tokens/{$this->token->id}")
        ->assertSuccessful();

    $this->assertDatabaseMissing('tokens', ['id' => $this->token->id]);
});

test('user cannot delete another user token', function (): void {
    $this->actingAs($this->user, 'dynamic')
        ->deleteJson("/api/users/{$this->otherUser->id}/tokens/{$this->otherToken->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('tokens', ['id' => $this->otherToken->id]);
});
