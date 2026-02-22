<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use JonaGoldman\Auth\Actions\Login;
use JonaGoldman\Auth\Actions\Logout;
use JonaGoldman\Auth\Tests\Fixtures\Token;
use JonaGoldman\Auth\Tests\Fixtures\User;

test('login authenticates with correct credentials', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $login = app(Login::class);
    $result = $login(['email' => $user->email, 'password' => 'password123']);

    expect($result)
        ->toBeInstanceOf(Authenticatable::class)
        ->and($result->getKey())->toBe($user->getKey());
});

test('login rejects bad credentials', function (): void {
    User::factory()->create(['password' => 'password123']);

    $login = app(Login::class);

    expect(fn () => $login(['email' => 'test@example.com', 'password' => 'wrong-password']))
        ->toThrow(ValidationException::class);
});

test('login rejects nonexistent user', function (): void {
    $login = app(Login::class);

    expect(fn () => $login(['email' => 'nope@example.com', 'password' => 'password123']))
        ->toThrow(ValidationException::class);
});

test('login uses custom field name in validation error', function (): void {
    $login = app(Login::class);

    try {
        $login(['email' => 'nope@example.com', 'password' => 'password123'], field: 'username');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('username');

        return;
    }

    $this->fail('Expected ValidationException was not thrown.');
});

test('login rejects unverified user', function (): void {
    $user = User::factory()->unverified()->create(['password' => 'password123']);

    $login = app(Login::class);

    expect(fn () => $login(['email' => $user->email, 'password' => 'password123']))
        ->toThrow(ValidationException::class);
});

test('login dispatches Attempting event', function (): void {
    Event::fake([Attempting::class]);

    $user = User::factory()->create(['password' => 'password123']);

    $login = app(Login::class);
    $login(['email' => $user->email, 'password' => 'password123']);

    Event::assertDispatched(Attempting::class);
});

test('login dispatches Failed event on bad credentials', function (): void {
    Event::fake([Failed::class]);

    User::factory()->create(['email' => 'test@example.com', 'password' => 'password123']);

    $login = app(Login::class);

    try {
        $login(['email' => 'test@example.com', 'password' => 'wrong']);
    } catch (ValidationException) {
        // expected
    }

    Event::assertDispatched(Failed::class);
});

test('stateful login establishes session', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $login = app(Login::class);
    $login(['email' => $user->email, 'password' => 'password123'], stateful: true);

    $guard = Auth::guard('session');

    expect($guard->check())->toBeTrue()
        ->and($guard->id())->toBe($user->getKey());
});

test('stateful login dispatches Login event', function (): void {
    Event::fake([LoginEvent::class]);

    $user = User::factory()->create(['password' => 'password123']);

    $login = app(Login::class);
    $login(['email' => $user->email, 'password' => 'password123'], stateful: true);

    Event::assertDispatched(LoginEvent::class, fn (LoginEvent $e) => $e->user->getKey() === $user->getKey());
});

test('stateful login dispatches Failed event for unverified user', function (): void {
    Event::fake([Failed::class]);

    $user = User::factory()->unverified()->create(['password' => 'password123']);

    $login = app(Login::class);

    try {
        $login(['email' => $user->email, 'password' => 'password123'], stateful: true);
    } catch (ValidationException) {
        // expected
    }

    Event::assertDispatched(Failed::class);
});

test('stateful login rejects bad credentials', function (): void {
    User::factory()->create(['password' => 'password123']);

    $login = app(Login::class);

    expect(fn () => $login(['email' => 'test@example.com', 'password' => 'wrong'], stateful: true))
        ->toThrow(ValidationException::class);
});

test('logout revokes the bearer token', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful();

    $logout = new Logout;
    $logout(request());

    $this->assertDatabaseMissing('tokens', ['id' => $token->getKey()]);
});

test('logout invalidates session for session-authenticated users', function (): void {
    $user = User::factory()->create();
    $user->setRelation('token', null);

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
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id);
});

test('unauthenticated request returns 401', function (): void {
    $this->getJson('/auth-test')
        ->assertUnauthorized();
});
