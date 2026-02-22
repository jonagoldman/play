<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Deplox\Shield\Enums\TokenType;
use Deplox\Shield\Resources\TokenResource;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;

test('resource includes all expected fields', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $resource = TokenResource::make($token)->toArray(request());

    expect($resource)->toHaveKeys([
        'id',
        'user_id',
        'name',
        'type',
        'expired',
        'expires_at',
        'last_used_at',
        'created_at',
        'updated_at',
    ]);
});

test('resource includes token field when plain is set', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken();

    $resource = TokenResource::make($token)->toArray(request());

    expect($resource)
        ->toHaveKey('token')
        ->and($resource['token'])->toBe($token->plain);
});

test('resource excludes token field when plain is not set', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    // Refresh to clear the plain value set by the factory afterCreating hook
    $token = Token::find($token->getKey());

    $resource = TokenResource::make($token)->resolve(request());

    expect($resource)->not->toHaveKey('token');
});

test('resource formats dates as ISO 8601 Zulu strings', function (): void {
    $user = User::factory()->create();

    Date::setTestNow('2025-06-15 12:30:00');

    $token = Token::factory()->for($user, 'owner')->create([
        'expires_at' => Date::parse('2026-06-15 12:30:00'),
    ]);

    $resource = TokenResource::make($token)->toArray(request());

    expect($resource['expires_at'])->toBe('2026-06-15T12:30:00Z')
        ->and($resource['created_at'])->toBe('2025-06-15T12:30:00Z');
});

test('resource includes expired boolean', function (): void {
    $user = User::factory()->create();

    $activeToken = Token::factory()->for($user, 'owner')->create([
        'expires_at' => Date::now()->addDay(),
    ]);

    $expiredToken = Token::factory()->for($user, 'owner')->create([
        'expires_at' => Date::now()->subDay(),
    ]);

    $activeResource = TokenResource::make($activeToken)->toArray(request());
    $expiredResource = TokenResource::make($expiredToken)->toArray(request());

    expect($activeResource['expired'])->toBeFalse()
        ->and($expiredResource['expired'])->toBeTrue();
});

test('resource type field returns token type', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken(type: TokenType::Remember);

    $resource = TokenResource::make($token)->toArray(request());

    expect($resource['type'])->toBe(TokenType::Remember);
});
