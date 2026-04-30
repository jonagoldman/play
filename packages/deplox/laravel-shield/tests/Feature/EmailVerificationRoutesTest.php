<?php

declare(strict_types=1);

use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    // Allow unverified users to authenticate so they can resend their verification email.
    app()->singleton(Shield::class, fn (): Shield => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'dpl_',
    ));

    Shield::registerEmailVerificationRoutes();
    app('router')->getRoutes()->refreshNameLookups();
    app(RateLimiter::class)->clear('verify-email|test|127.0.0.1');
});

test('POST email/verification-notification sends notification for unverified user', function (): void {
    Illuminate\Support\Facades\Notification::fake();

    $user = User::factory()->unverified()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->postJson('/email/verification-notification')
        ->assertOk()
        ->assertJsonPath('status', 'verification-link-sent');
});

test('POST email/verification-notification returns already-verified for verified user', function (): void {
    Illuminate\Support\Facades\Notification::fake();

    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $this->withToken($token->plain, 'Bearer')
        ->postJson('/email/verification-notification')
        ->assertOk()
        ->assertJsonPath('status', 'already-verified');
});

test('POST email/verification-notification returns 401 when unauthenticated', function (): void {
    $this->postJson('/email/verification-notification')->assertStatus(401);
});

test('GET email/verify/{id}/{hash} marks user verified with valid signed URL', function (): void {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('status', 'verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(Verified::class);
});

test('GET email/verify/{id}/{hash} returns 403 when signature invalid', function (): void {
    $user = User::factory()->unverified()->create();

    $this->getJson('/email/verify/'.$user->getKey().'/'.sha1($user->email))
        ->assertStatus(403);
});

test('GET email/verify/{id}/{hash} returns 422 when hash does not match user email', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('attacker@example.com'),
    ]);

    $this->getJson($url)
        ->assertStatus(422)
        ->assertJsonPath('status', 'verification-failed');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
