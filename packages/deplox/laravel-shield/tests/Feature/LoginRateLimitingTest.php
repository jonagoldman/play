<?php

declare(strict_types=1);

use Deplox\Shield\Actions\Login;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

test('failed login increments rate limiter', function (): void {
    User::factory()->create(['email' => 'test@example.com', 'password' => 'password123']);

    $login = app(Login::class);

    try {
        $login(['email' => 'test@example.com', 'password' => 'wrong']);
    } catch (ValidationException) {
        // expected
    }

    $limiter = app(RateLimiter::class);
    $key = mb_strtolower('test@example.com').'|127.0.0.1';

    expect($limiter->attempts($key))->toBe(1);
});

test('successful login clears rate limiter', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com', 'password' => 'password123']);

    $login = app(Login::class);

    // Fail a few times first
    for ($i = 0; $i < 3; $i++) {
        try {
            $login(['email' => 'test@example.com', 'password' => 'wrong']);
        } catch (ValidationException) {
            // expected
        }
    }

    // Successful login
    $login(['email' => 'test@example.com', 'password' => 'password123']);

    $limiter = app(RateLimiter::class);
    $key = mb_strtolower('test@example.com').'|127.0.0.1';

    expect($limiter->attempts($key))->toBe(0);
});

test('lockout returns 429 after too many attempts', function (): void {
    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        maxLoginAttempts: 3,
        loginDecaySeconds: 60,
        validateUser: fn ($user) => $user->verified_at !== null,
    ));

    User::factory()->create(['email' => 'test@example.com', 'password' => 'password123']);

    $login = app(Login::class);

    // Exhaust attempts
    for ($i = 0; $i < 3; $i++) {
        try {
            $login(['email' => 'test@example.com', 'password' => 'wrong']);
        } catch (ValidationException) {
            // expected
        }
    }

    // Next attempt should be throttled
    try {
        $login(['email' => 'test@example.com', 'password' => 'password123']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(429)
            ->and($e->errors())->toHaveKey('email');
    }
});

test('lockout fires Lockout event', function (): void {
    Event::fake([Lockout::class]);

    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        maxLoginAttempts: 2,
        loginDecaySeconds: 60,
        validateUser: fn ($user) => $user->verified_at !== null,
    ));

    User::factory()->create(['email' => 'test@example.com', 'password' => 'password123']);

    $login = app(Login::class);

    // Exhaust attempts
    for ($i = 0; $i < 2; $i++) {
        try {
            $login(['email' => 'test@example.com', 'password' => 'wrong']);
        } catch (ValidationException) {
            // expected
        }
    }

    // Trigger lockout
    try {
        $login(['email' => 'test@example.com', 'password' => 'password123']);
    } catch (ValidationException) {
        // expected
    }

    Event::assertDispatched(Lockout::class);
});

test('lockout uses custom field name', function (): void {
    $this->app->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        maxLoginAttempts: 1,
        loginDecaySeconds: 60,
        validateUser: fn ($user) => $user->verified_at !== null,
    ));

    User::factory()->create(['email' => 'test@example.com', 'password' => 'password123']);

    $login = app(Login::class);

    try {
        $login(['email' => 'test@example.com', 'password' => 'wrong'], field: 'username');
    } catch (ValidationException) {
        // expected
    }

    try {
        $login(['email' => 'test@example.com', 'password' => 'password123'], field: 'username');
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(429)
            ->and($e->errors())->toHaveKey('username');
    }
});

test('rate limiter uses transliterated lowercase key', function (): void {
    User::factory()->create(['email' => 'Über@example.com', 'password' => 'password123']);

    $login = app(Login::class);

    try {
        $login(['email' => 'Über@example.com', 'password' => 'wrong']);
    } catch (ValidationException) {
        // expected
    }

    $limiter = app(RateLimiter::class);
    $key = 'uber@example.com|127.0.0.1';

    expect($limiter->attempts($key))->toBe(1);
});
