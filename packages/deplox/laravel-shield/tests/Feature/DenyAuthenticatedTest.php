<?php

declare(strict_types=1);

use Deplox\Shield\Middlewares\DenyAuthenticated;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(DenyAuthenticated::class.':dynamic')
        ->get('/guest-only', fn () => response()->json(['status' => 'ok']));
});

test('allows unauthenticated request through', function (): void {
    $this->getJson('/guest-only')
        ->assertSuccessful()
        ->assertJsonPath('status', 'ok');
});

test('blocks session-authenticated user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'dynamic')
        ->getJson('/guest-only')
        ->assertForbidden();
});

test('blocks token-authenticated user', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/guest-only')
        ->assertForbidden();
});

test('allows request when specified guard has no user', function (): void {
    Route::middleware(DenyAuthenticated::class.':session')
        ->get('/guest-session-only', fn () => response()->json(['status' => 'ok']));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/guest-session-only')
        ->assertSuccessful();
});
