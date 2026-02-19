<?php

declare(strict_types=1);

use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\Tests\Fixtures\Token;
use JonaGoldman\Auth\Tests\Fixtures\User;

test('decorateToken produces correct format with prefix', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        tokenPrefix: 'test_',
    );

    $random = 'abcdefghijklmnopqrstuvwxyz012345678901234567890A';
    $decorated = $config->decorateToken($random);

    expect($decorated)
        ->toStartWith('test_')
        ->toContain($random)
        ->toHaveLength(mb_strlen('test_') + mb_strlen($random) + 8);
});

test('extractRandom round-trips correctly', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        tokenPrefix: 'test_',
    );

    $random = 'abcdefghijklmnopqrstuvwxyz012345678901234567890A';
    $decorated = $config->decorateToken($random);

    expect($config->extractRandom($decorated))->toBe($random);
});

test('extractRandom returns null for wrong prefix', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        tokenPrefix: 'test_',
    );

    $other = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        tokenPrefix: 'other_',
    );

    $decorated = $other->decorateToken('abcdefghijklmnopqrstuvwxyz012345678901234567890A');

    expect($config->extractRandom($decorated))->toBeNull();
});

test('extractRandom returns null for tampered checksum', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        tokenPrefix: 'test_',
    );

    $random = 'abcdefghijklmnopqrstuvwxyz012345678901234567890A';
    $decorated = $config->decorateToken($random);

    // Tamper with the last character of the checksum
    $tampered = mb_substr($decorated, 0, -1).'X';

    expect($config->extractRandom($tampered))->toBeNull();
});

test('extractRandom returns null for empty or too-short token', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        tokenPrefix: 'test_',
    );

    expect($config->extractRandom(''))->toBeNull()
        ->and($config->extractRandom('test_'))->toBeNull()
        ->and($config->extractRandom('test_short'))->toBeNull();
});

test('empty prefix preserves original behavior', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        tokenPrefix: '',
    );

    $random = 'abcdefghijklmnopqrstuvwxyz012345678901234567890A';

    expect($config->decorateToken($random))->toBe($random)
        ->and($config->extractRandom($random))->toBe($random);
});

test('authentication works with prefixed token', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    expect($token->plain)->toStartWith('dpl_');

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful();
});

test('authentication rejects tampered prefixed token', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user)->create();

    $tampered = mb_substr($token->plain, 0, -1).'X';

    $this->withToken($tampered, 'Bearer')
        ->getJson('/auth-test')
        ->assertUnauthorized();
});
