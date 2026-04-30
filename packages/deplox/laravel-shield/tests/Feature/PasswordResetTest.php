<?php

declare(strict_types=1);

use Deplox\Shield\Actions\ResetPassword;
use Deplox\Shield\Actions\SendPasswordReset;
use Deplox\Shield\Enums\RevokeOnPasswordChange;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Contracts\Auth\PasswordBroker as PasswordBrokerContract;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function shieldWithReset(RevokeOnPasswordChange $mode = RevokeOnPasswordChange::Bearer): void
{
    app()->singleton(Shield::class, fn () => new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'dpl_',
        revokeOnPasswordChange: $mode,
        validateUser: fn ($user): bool => $user->verified_at !== null,
    ));
}

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
});

test('SendPasswordReset returns RESET_LINK_SENT for an existing user', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'a@example.com']);

    $status = app(SendPasswordReset::class)(['email' => 'a@example.com']);

    expect($status)->toBe(PasswordBrokerContract::RESET_LINK_SENT);

    Notification::assertSentTo(
        User::where('email', 'a@example.com')->first(),
        ResetPasswordNotification::class,
    );
});

test('SendPasswordReset returns INVALID_USER when no user matches', function (): void {
    Notification::fake();

    $status = app(SendPasswordReset::class)(['email' => 'missing@example.com']);

    expect($status)->toBe(PasswordBrokerContract::INVALID_USER);

    Notification::assertNothingSent();
});

test('SendPasswordReset returns RESET_THROTTLED on rapid second call', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'a@example.com']);

    app(SendPasswordReset::class)(['email' => 'a@example.com']);
    $second = app(SendPasswordReset::class)(['email' => 'a@example.com']);

    expect($second)->toBe(PasswordBrokerContract::RESET_THROTTLED);
});

test('SendPasswordReset throws ValidationException on missing email', function (): void {
    expect(fn () => app(SendPasswordReset::class)([]))
        ->toThrow(ValidationException::class);
});

test('ResetPassword updates the password hash and returns PASSWORD_RESET', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'a@example.com', 'password' => Hash::make('old-password')]);
    $broker = app(PasswordBrokerFactory::class)->broker();
    $token = $broker->createToken($user);

    $status = app(ResetPassword::class)([
        'email' => 'a@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
        'token' => $token,
    ]);

    expect($status)->toBe(PasswordBrokerContract::PASSWORD_RESET)
        ->and(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('ResetPassword returns INVALID_TOKEN for a stale token', function (): void {
    User::factory()->create(['email' => 'a@example.com']);

    $status = app(ResetPassword::class)([
        'email' => 'a@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
        'token' => 'never-existed',
    ]);

    expect($status)->toBe(PasswordBrokerContract::INVALID_TOKEN);
});

test('ResetPassword rejects mismatched password confirmation', function (): void {
    expect(fn () => app(ResetPassword::class)([
        'email' => 'a@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'different',
        'token' => 'whatever',
    ]))->toThrow(ValidationException::class);
});

test('ResetPassword triggers token revocation via the registered listener', function (): void {
    shieldWithReset(RevokeOnPasswordChange::Bearer);
    app(Shield::class)->boot(app(), app('Illuminate\\Contracts\\Http\\Kernel'), app('auth'));

    $user = User::factory()->create(['email' => 'a@example.com']);
    $token = Token::factory()->for($user, 'owner')->create();

    $broker = app(PasswordBrokerFactory::class)->broker();
    $resetToken = $broker->createToken($user);

    app(ResetPassword::class)([
        'email' => 'a@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
        'token' => $resetToken,
    ]);

    expect(Token::query()->where($token->getKeyName(), $token->getKey())->exists())->toBeFalse();
});
