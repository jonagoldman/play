<?php

declare(strict_types=1);

use Deplox\Support\Auth\Passwords\DatabaseTokenRepository;
use Deplox\Support\Auth\Passwords\PasswordBroker;
use Deplox\Support\Tests\Fixtures\ResettableUser;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\PasswordBroker as PasswordBrokerContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('resettable_users', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('email')->unique();
        $table->string('password');
    });

    Schema::create('reset_tokens', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('email')->unique();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    $repo = new DatabaseTokenRepository(
        connection: DB::connection(),
        hasher: Hash::driver(),
        key: 'base64:'.base64_encode(str_repeat('a', 32)),
        table: 'reset_tokens',
        expires: 3600,
        throttle: 60,
    );

    $this->users = new EloquentUserProvider(Hash::driver(), ResettableUser::class);
    $this->broker = new PasswordBroker($this->users, $repo);
});

test('sendResetLink returns INVALID_USER when no user matches', function (): void {
    $status = $this->broker->sendResetLink(['email' => 'missing@example.com']);

    expect($status)->toBe(PasswordBrokerContract::INVALID_USER);
});

test('sendResetLink returns RESET_LINK_SENT for an existing user', function (): void {
    ResettableUser::create(['email' => 'a@example.com', 'password' => Hash::make('pw')]);

    $captured = null;
    $status = $this->broker->sendResetLink(
        ['email' => 'a@example.com'],
        function ($user, $token) use (&$captured): void {
            $captured = ['email' => $user->email, 'token' => $token];
        },
    );

    expect($status)->toBe(PasswordBrokerContract::RESET_LINK_SENT)
        ->and($captured['email'])->toBe('a@example.com')
        ->and($captured['token'])->toBeString();
});

test('sendResetLink returns RESET_THROTTLED when called twice in quick succession', function (): void {
    ResettableUser::create(['email' => 'a@example.com', 'password' => Hash::make('pw')]);

    $this->broker->sendResetLink(['email' => 'a@example.com'], fn () => null);
    $second = $this->broker->sendResetLink(['email' => 'a@example.com'], fn () => null);

    expect($second)->toBe(PasswordBrokerContract::RESET_THROTTLED);
});

test('reset returns INVALID_USER when no user matches', function (): void {
    $status = $this->broker->reset(
        ['email' => 'missing@example.com', 'password' => 'new', 'token' => 'whatever'],
        fn () => null,
    );

    expect($status)->toBe(PasswordBrokerContract::INVALID_USER);
});

test('reset returns INVALID_TOKEN when token does not match', function (): void {
    ResettableUser::create(['email' => 'a@example.com', 'password' => Hash::make('pw')]);

    $status = $this->broker->reset(
        ['email' => 'a@example.com', 'password' => 'new', 'token' => 'wrong'],
        fn () => null,
    );

    expect($status)->toBe(PasswordBrokerContract::INVALID_TOKEN);
});

test('reset returns PASSWORD_RESET and invokes callback when token matches', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => Hash::make('pw')]);

    $token = $this->broker->createToken($user);

    $captured = null;
    $status = $this->broker->reset(
        ['email' => 'a@example.com', 'password' => 'newpw', 'token' => $token],
        function ($user, $newPassword) use (&$captured): void {
            $captured = $newPassword;
        },
    );

    expect($status)->toBe(PasswordBrokerContract::PASSWORD_RESET)
        ->and($captured)->toBe('newpw');
});

test('reset deletes the token after success', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => Hash::make('pw')]);
    $token = $this->broker->createToken($user);

    $this->broker->reset(
        ['email' => 'a@example.com', 'password' => 'newpw', 'token' => $token],
        fn () => null,
    );

    expect(DB::table('reset_tokens')->where('email', 'a@example.com')->exists())->toBeFalse();
});

test('tokenExists matches a created token', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => Hash::make('pw')]);
    $token = $this->broker->createToken($user);

    expect($this->broker->tokenExists($user, $token))->toBeTrue()
        ->and($this->broker->tokenExists($user, 'wrong'))->toBeFalse();
});
