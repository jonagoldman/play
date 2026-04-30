<?php

declare(strict_types=1);

use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasTable('password_reset_tokens')) {
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    config(['auth.passwords.users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ]]);

    config(['auth.providers.users' => [
        'driver' => 'eloquent',
        'model' => User::class,
    ]]);

    config(['auth.defaults.passwords' => 'users']);

    app()->singleton(PasswordBrokerFactory::class, fn ($app): PasswordBrokerManager => new PasswordBrokerManager($app));

    Shield::registerPasswordResetRoutes();
});

test('POST password/email returns 200 with status when user exists', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'a@example.com']);

    $this->postJson('/password/email', ['email' => 'a@example.com'])
        ->assertOk()
        ->assertJsonStructure(['status']);

    Notification::assertSentTo(
        User::where('email', 'a@example.com')->first(),
        ResetPasswordNotification::class,
    );
});

test('POST password/email returns 422 for unknown email', function (): void {
    $this->postJson('/password/email', ['email' => 'missing@example.com'])
        ->assertStatus(422);
});

test('POST password/email returns 422 for invalid input', function (): void {
    $this->postJson('/password/email', [])
        ->assertStatus(422);
});

test('POST password/reset succeeds with a valid token', function (): void {
    $user = User::factory()->create(['email' => 'a@example.com', 'password' => Hash::make('old')]);
    $token = app(PasswordBrokerFactory::class)->broker()->createToken($user);

    $this->postJson('/password/reset', [
        'email' => 'a@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
        'token' => $token,
    ])->assertOk();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('POST password/reset returns 422 with bad token', function (): void {
    User::factory()->create(['email' => 'a@example.com']);

    $this->postJson('/password/reset', [
        'email' => 'a@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
        'token' => 'invalid',
    ])->assertStatus(422);
});
