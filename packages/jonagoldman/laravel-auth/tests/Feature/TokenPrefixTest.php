<?php

declare(strict_types=1);

use JonaGoldman\Auth\Shield;
use JonaGoldman\Auth\Tests\Fixtures\Token;
use JonaGoldman\Auth\Tests\Fixtures\User;

test('decorateToken produces correct format with prefix', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'test_',
    );

    $random = 'abcdefghijklmnopqrstuvwxyz012345678901234567890A';
    $decorated = $shield->decorateToken($random);

    expect($decorated)
        ->toStartWith('test_')
        ->toContain($random)
        ->toHaveLength(mb_strlen('test_') + mb_strlen($random) + 8);
});

test('extractRandom round-trips correctly', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'test_',
    );

    $random = 'abcdefghijklmnopqrstuvwxyz012345678901234567890A';
    $decorated = $shield->decorateToken($random);

    expect($shield->extractRandom($decorated))->toBe($random);
});

test('extractRandom returns null for wrong prefix', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'test_',
    );

    $other = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'other_',
    );

    $decorated = $other->decorateToken('abcdefghijklmnopqrstuvwxyz012345678901234567890A');

    expect($shield->extractRandom($decorated))->toBeNull();
});

test('extractRandom returns null for tampered checksum', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'test_',
    );

    $random = 'abcdefghijklmnopqrstuvwxyz012345678901234567890A';
    $decorated = $shield->decorateToken($random);

    // Tamper with the last character of the checksum
    $tampered = mb_substr($decorated, 0, -1).'X';

    expect($shield->extractRandom($tampered))->toBeNull();
});

test('extractRandom returns null for empty or too-short token', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: 'test_',
    );

    expect($shield->extractRandom(''))->toBeNull()
        ->and($shield->extractRandom('test_'))->toBeNull()
        ->and($shield->extractRandom('test_short'))->toBeNull();
});

test('empty prefix preserves original behavior', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        prefix: '',
    );

    $random = 'abcdefghijklmnopqrstuvwxyz012345678901234567890A';

    expect($shield->decorateToken($random))->toBe($random)
        ->and($shield->extractRandom($random))->toBe($random);
});

test('authentication works with prefixed token', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    expect($token->plain)->toStartWith('dpl_');

    $this->withToken($token->plain, 'Bearer')
        ->getJson('/auth-test')
        ->assertSuccessful();
});

test('authentication rejects tampered prefixed token', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $tampered = mb_substr($token->plain, 0, -1).'X';

    $this->withToken($tampered, 'Bearer')
        ->getJson('/auth-test')
        ->assertUnauthorized();
});
