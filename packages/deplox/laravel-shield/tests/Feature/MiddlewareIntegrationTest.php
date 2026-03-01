<?php

declare(strict_types=1);

use Deplox\Shield\Contracts\IsAuthToken;
use Deplox\Shield\Middlewares\DenyAuthenticated;
use Deplox\Shield\Middlewares\ResolveCurrentUser;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

test('resolve current user works with bearer token authentication', function (): void {
    Route::middleware(['auth:dynamic', ResolveCurrentUser::class, SubstituteBindings::class])
        ->get('/integration/users/{user}', fn (User $user) => response()->json([
            'id' => $user->getKey(),
        ]));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/integration/users/me')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});

test('deny authenticated allows request with expired bearer token and deletes it', function (): void {
    Route::middleware(DenyAuthenticated::class.':dynamic')
        ->get('/integration/guest', fn () => response()->json(['status' => 'ok']));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create([
        'expires_at' => now()->subMinute(),
    ]);

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/integration/guest')
        ->assertSuccessful();

    $this->assertDatabaseMissing('tokens', ['id' => $token->getKey()]);
});

test('deny authenticated allows request when bearer token belongs to unverified user', function (): void {
    Route::middleware(DenyAuthenticated::class.':dynamic')
        ->get('/integration/guest-unverified', fn () => response()->json(['status' => 'ok']));

    $user = User::factory()->unverified()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/integration/guest-unverified')
        ->assertSuccessful();
});

test('deny authenticated detects authentication via custom extract token callback', function (): void {
    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'dpl_',
        validateUser: fn ($user) => $user->verified_at !== null,
        extractToken: fn (Request $request) => $request->query('api_token'),
    ));

    Route::middleware(DenyAuthenticated::class.':dynamic')
        ->get('/integration/guest-custom', fn () => response()->json(['status' => 'ok']));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->getJson('/integration/guest-custom?api_token='.$token->plain)
        ->assertForbidden();
});

test('deny authenticated allows request when validate token callback rejects', function (): void {
    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'dpl_',
        validateUser: fn ($user) => $user->verified_at !== null,
        validateToken: fn (IsAuthToken $token, $request) => false,
    ));

    Route::middleware(DenyAuthenticated::class.':dynamic')
        ->get('/integration/guest-rejected', fn () => response()->json(['status' => 'ok']));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/integration/guest-rejected')
        ->assertSuccessful();
});

test('resolve current user preserves token relation from bearer auth', function (): void {
    Route::middleware(['auth:dynamic', ResolveCurrentUser::class, SubstituteBindings::class])
        ->get('/integration/users/{user}/token-check', fn (User $user) => response()->json([
            'id' => $user->getKey(),
            'has_token' => $user->relationLoaded('token'),
            'token_id' => $user->token?->getKey(),
        ]));

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/integration/users/me/token-check')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('has_token', true)
        ->assertJsonPath('token_id', $token->getKey());
});

test('deny authenticated blocks when both session and bearer token are present', function (): void {
    Route::middleware(DenyAuthenticated::class.':dynamic')
        ->get('/integration/guest-both', fn () => response()->json(['status' => 'ok']));

    $sessionUser = User::factory()->create();
    $tokenUser = User::factory()->create();
    $token = Token::factory()->for($tokenUser, 'owner')->create();

    $this->actingAs($sessionUser, 'session')
        ->withToken($token->plain, 'Bearer')
        ->getJson('/integration/guest-both')
        ->assertForbidden();
});

test('resolve current user with bearer auth leaves non-me values for normal binding', function (): void {
    Route::middleware(['auth:dynamic', ResolveCurrentUser::class, SubstituteBindings::class])
        ->get('/integration/users/{user}/profile', fn (User $user) => response()->json([
            'id' => $user->getKey(),
        ]));

    $authedUser = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = Token::factory()->for($authedUser, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/integration/users/'.$otherUser->id.'/profile')
        ->assertSuccessful()
        ->assertJsonPath('id', $otherUser->id);
});
