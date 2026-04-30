<?php

declare(strict_types=1);

use Deplox\Support\Auth\Passwords\DatabaseTokenRepository;
use Deplox\Support\Tests\Fixtures\ResettableUser;
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

    $this->repository = new DatabaseTokenRepository(
        connection: DB::connection(),
        hasher: Hash::driver(),
        key: 'base64:'.base64_encode(str_repeat('a', 32)),
        table: 'reset_tokens',
        expires: 3600,
        throttle: 60,
    );
});

test('create stores a hashed token and returns the plaintext', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => 'x']);

    $plain = $this->repository->create($user);

    expect($plain)->toBeString()->and(mb_strlen($plain))->toBe(64);

    $row = (array) DB::table('reset_tokens')->where('email', 'a@example.com')->first();
    expect($row['token'])->not->toBe($plain)
        ->and(Hash::check($plain, $row['token']))->toBeTrue();
});

test('exists returns true for matching token within expiry', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => 'x']);
    $plain = $this->repository->create($user);

    expect($this->repository->exists($user, $plain))->toBeTrue();
});

test('exists returns false for wrong token', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => 'x']);
    $this->repository->create($user);

    expect($this->repository->exists($user, 'wrong-token'))->toBeFalse();
});

test('exists returns false when token has expired', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => 'x']);
    $plain = $this->repository->create($user);

    $this->travel(3601)->seconds();

    expect($this->repository->exists($user, $plain))->toBeFalse();
});

test('recentlyCreatedToken returns true within throttle window', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => 'x']);
    $this->repository->create($user);

    expect($this->repository->recentlyCreatedToken($user))->toBeTrue();
});

test('recentlyCreatedToken returns false after throttle window', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => 'x']);
    $this->repository->create($user);

    $this->travel(61)->seconds();

    expect($this->repository->recentlyCreatedToken($user))->toBeFalse();
});

test('delete removes the user token row', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => 'x']);
    $this->repository->create($user);

    $this->repository->delete($user);

    expect(DB::table('reset_tokens')->where('email', 'a@example.com')->exists())->toBeFalse();
});

test('deleteExpired removes only rows older than the expiry', function (): void {
    $u1 = ResettableUser::create(['email' => 'old@example.com', 'password' => 'x']);
    $u2 = ResettableUser::create(['email' => 'new@example.com', 'password' => 'x']);

    $this->repository->create($u1);
    $this->travel(3601)->seconds();
    $this->repository->create($u2);

    $this->repository->deleteExpired();

    expect(DB::table('reset_tokens')->where('email', 'old@example.com')->exists())->toBeFalse()
        ->and(DB::table('reset_tokens')->where('email', 'new@example.com')->exists())->toBeTrue();
});

test('create replaces an existing token for the same user', function (): void {
    $user = ResettableUser::create(['email' => 'a@example.com', 'password' => 'x']);

    $this->repository->create($user);
    $second = $this->repository->create($user);

    expect(DB::table('reset_tokens')->where('email', 'a@example.com')->count())->toBe(1)
        ->and($this->repository->exists($user, $second))->toBeTrue();
});
