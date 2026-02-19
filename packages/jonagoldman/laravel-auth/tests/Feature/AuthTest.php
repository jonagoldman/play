<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use JonaGoldman\Auth\Actions\Login;
use JonaGoldman\Auth\Actions\Logout;
use JonaGoldman\Auth\Tests\Fixtures\Token;
use JonaGoldman\Auth\Tests\Fixtures\User;
use JonaGoldman\Auth\TransientToken;

test('login authenticates with correct credentials and creates a token', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $login = new Login;
    $token = $login($user, 'password123', tokenName: 'auth');

    expect($token)
        ->toBeInstanceOf(Token::class)
        ->and($token->plain)->not->toBeNull()
        ->and($token->name)->toBe('auth');
});

test('login rejects bad credentials', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $login = new Login;

    expect(fn () => $login($user, 'wrong-password'))
        ->toThrow(ValidationException::class);
});

test('login rejects nonexistent user', function (): void {
    $login = new Login;

    expect(fn () => $login(null, 'password123'))
        ->toThrow(ValidationException::class);
});

test('login uses custom field name in validation error', function (): void {
    $login = new Login;

    try {
        $login(null, 'password123', field: 'username');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('username');

        return;
    }

    $this->fail('Expected ValidationException was not thrown.');
});

test('logout revokes the bearer token', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful();

    $logout = new Logout;
    $logout(request());

    $this->assertDatabaseMissing('tokens', ['id' => $token->getKey()]);
});

test('logout invalidates session for session-authenticated users', function (): void {
    $user = User::factory()->create();
    $user->setRelation('token', new TransientToken);

    $this->actingAs($user, 'dynamic');

    $session = session()->driver();
    $session->start();
    $session->put('test_key', 'test_value');
    $originalId = $session->getId();

    $request = request();
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession($session);

    $logout = new Logout;
    $logout($request);

    expect($session->get('test_key'))->toBeNull()
        ->and($session->getId())->not->toBe($originalId);
});

test('authenticated user is returned via auth guard', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});

test('unauthenticated request returns 401', function (): void {
    $this->getJson('/auth-test')
        ->assertUnauthorized();
});
