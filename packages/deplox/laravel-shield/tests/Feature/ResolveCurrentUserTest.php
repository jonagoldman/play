<?php

declare(strict_types=1);

use Deplox\Shield\Middlewares\ResolveCurrentUser;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['auth:dynamic', ResolveCurrentUser::class, Illuminate\Routing\Middleware\SubstituteBindings::class])
        ->get('/users/{user}', fn (User $user) => response()->json([
            'id' => $user->getKey(),
        ]));
});

test('replaces "me" with authenticated user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'dynamic')
        ->getJson('/users/me')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});

test('leaves non-"me" values for normal binding', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user, 'dynamic')
        ->getJson('/users/'.$other->id)
        ->assertSuccessful()
        ->assertJsonPath('id', $other->id);
});

test('returns 401 when "me" used without authentication', function (): void {
    $this->getJson('/users/me')
        ->assertUnauthorized();
});

test('works with custom parameter name', function (): void {
    Route::middleware(['auth:dynamic', ResolveCurrentUser::class.':author', Illuminate\Routing\Middleware\SubstituteBindings::class])
        ->get('/posts/{author}', fn (User $author) => response()->json([
            'id' => $author->getKey(),
        ]));

    $user = User::factory()->create();

    $this->actingAs($user, 'dynamic')
        ->getJson('/posts/me')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});
