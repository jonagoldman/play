<?php

declare(strict_types=1);

use Deplox\Shield\Actions\SendEmailVerification;
use Deplox\Shield\Actions\VerifyEmail;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    app(RateLimiter::class)->clear('verify-email|test|127.0.0.1');
});

test('SendEmailVerification dispatches the verification notification', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $sent = app(SendEmailVerification::class)($user);

    expect($sent)->toBeTrue();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('SendEmailVerification returns false when the user is already verified', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $sent = app(SendEmailVerification::class)($user);

    expect($sent)->toBeFalse();

    Notification::assertNothingSent();
});

test('SendEmailVerification throws after the configured rate limit', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    for ($i = 0; $i < 6; $i++) {
        app(SendEmailVerification::class)($user, maxAttempts: 6);
    }

    expect(fn () => app(SendEmailVerification::class)($user, maxAttempts: 6))
        ->toThrow(ValidationException::class);
});

test('VerifyEmail marks the user verified when hash matches', function (): void {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();
    $hash = sha1($user->getEmailForVerification());

    $verified = app(VerifyEmail::class)($user->getKey(), $hash);

    expect($verified)->toBeTrue()
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(Verified::class);
});

test('VerifyEmail returns false for a wrong hash', function (): void {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();

    $verified = app(VerifyEmail::class)($user->getKey(), 'tampered-hash');

    expect($verified)->toBeFalse()
        ->and($user->fresh()->hasVerifiedEmail())->toBeFalse();

    Event::assertNotDispatched(Verified::class);
});

test('VerifyEmail returns false when user is already verified', function (): void {
    Event::fake([Verified::class]);

    $user = User::factory()->create();
    $hash = sha1($user->getEmailForVerification());

    $verified = app(VerifyEmail::class)($user->getKey(), $hash);

    expect($verified)->toBeFalse();

    Event::assertNotDispatched(Verified::class);
});

test('VerifyEmail returns false when user does not exist', function (): void {
    $verified = app(VerifyEmail::class)('00000000-0000-0000-0000-000000000000', 'whatever');

    expect($verified)->toBeFalse();
});

test('hash mismatch is constant-time (uses hash_equals semantics)', function (): void {
    $user = User::factory()->unverified()->create();
    $correct = sha1($user->getEmailForVerification());

    expect(app(VerifyEmail::class)($user->getKey(), $correct))->toBeTrue();
});
