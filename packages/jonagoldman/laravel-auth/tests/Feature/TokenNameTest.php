<?php

declare(strict_types=1);

use JonaGoldman\Auth\Actions\Login;
use JonaGoldman\Auth\Resources\TokenResource;
use JonaGoldman\Auth\Tests\Fixtures\User;

test('tokens can be created with a name', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken(name: 'Mobile App');

    expect($token->name)->toBe('Mobile App');
    $this->assertDatabaseHas('tokens', [
        'id' => $token->getKey(),
        'name' => 'Mobile App',
    ]);
});

test('token name appears in resource output', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken(name: 'API Token');

    $resource = (new TokenResource($token))->toArray(request());

    expect($resource)
        ->toHaveKey('id', $token->id)
        ->toHaveKey('name', 'API Token');
});

test('login action creates tokens with custom name', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $login = new Login;
    $token = $login($user, 'password123', tokenName: 'auth');

    expect($token->name)->toBe('auth');
});

test('tokens can be created without a name', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken();

    expect($token->name)->toBeNull();
});
