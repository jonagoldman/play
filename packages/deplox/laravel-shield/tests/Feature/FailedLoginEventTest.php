<?php

declare(strict_types=1);

use Deplox\Shield\Actions\Login;
use Deplox\Shield\Events\FailedLogin;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

test('FailedLogin event fires when credentials are wrong', function (): void {
    Event::fake([FailedLogin::class]);

    User::factory()->create(['email' => 'a@example.com', 'password' => 'correct-pw']);

    try {
        app(Login::class)(['email' => 'a@example.com', 'password' => 'wrong-pw']);
    } catch (ValidationException) {
        // expected
    }

    Event::assertDispatched(FailedLogin::class, fn (FailedLogin $e): bool => $e->field === 'email'
        && $e->identifier === 'a@example.com'
        && $e->ip !== null);
});

test('FailedLogin uses the supplied custom field name', function (): void {
    Event::fake([FailedLogin::class]);

    try {
        app(Login::class)(['username' => 'nope', 'password' => 'pw'], field: 'username');
    } catch (ValidationException) {
        // expected
    }

    Event::assertDispatched(FailedLogin::class, fn (FailedLogin $e): bool => $e->field === 'username' && $e->identifier === 'nope');
});

test('FailedLogin does not fire on successful login', function (): void {
    Event::fake([FailedLogin::class]);

    $user = User::factory()->create(['email' => 'ok@example.com', 'password' => 'correct-pw']);

    app(Login::class)(['email' => 'ok@example.com', 'password' => 'correct-pw']);

    Event::assertNotDispatched(FailedLogin::class);
});

test('FailedLogin does not include the submitted password', function (): void {
    Event::fake([FailedLogin::class]);

    User::factory()->create(['email' => 'a@example.com', 'password' => 'pw']);

    try {
        app(Login::class)(['email' => 'a@example.com', 'password' => 'super-secret-leak']);
    } catch (ValidationException) {
        // expected
    }

    Event::assertDispatched(FailedLogin::class, function (FailedLogin $e): bool {
        $serialized = serialize($e);

        return ! str_contains($serialized, 'super-secret-leak');
    });
});
