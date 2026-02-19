<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\Enums\TokenType;
use JonaGoldman\Auth\Tests\Fixtures\Token;
use JonaGoldman\Auth\Tests\Fixtures\User;

test('createToken creates a bearer token with default expiration', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken();

    expect($token)
        ->toBeInstanceOf(Token::class)
        ->and($token->type)->toBe(TokenType::Bearer)
        ->and($token->name)->toBeNull()
        ->and($token->expires_at)->not->toBeNull();
});

test('createToken accepts custom type, expiration, and name', function (): void {
    $user = User::factory()->create();
    $expiresAt = Date::now()->addHour();

    $token = $user->createToken(
        type: TokenType::Remember,
        expiresAt: $expiresAt,
        name: 'My Device',
    );

    expect($token->type)->toBe(TokenType::Remember)
        ->and($token->name)->toBe('My Device')
        ->and($token->expires_at->timestamp)->toBe($expiresAt->timestamp);
});

test('createToken exposes plain attribute after creation', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken();

    // 4 (prefix) + 48 (random) + 8 (crc32b) = 60
    expect($token->plain)
        ->toBeString()
        ->toStartWith('dpl_')
        ->toHaveLength(60);
});

test('createToken stores hashed token in database', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken();

    $random = app(AuthConfig::class)->extractRandom($token->plain);

    $this->assertDatabaseHas('tokens', [
        'id' => $token->getKey(),
        'token' => hash('sha256', $random),
    ]);
});

test('createToken with no default expiration creates token without expiry', function (): void {
    $user = User::factory()->create();

    $this->app->singleton(AuthConfig::class, fn () => new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        defaultTokenExpiration: null,
    ));

    $token = $user->createToken();

    expect($token->expires_at)->toBeNull();
});
