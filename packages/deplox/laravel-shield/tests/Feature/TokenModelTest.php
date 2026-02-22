<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;

test('findByToken returns null for empty string', function (): void {
    expect(Token::findByToken(''))->toBeNull();
});

test('findByToken returns null for invalid checksum', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    // Corrupt the last character (checksum portion)
    $corrupted = mb_substr($token->plain, 0, -1).'X';

    expect(Token::findByToken($corrupted))->toBeNull();
});

test('findByToken returns null for nonexistent token', function (): void {
    $shield = app(Shield::class);
    $fabricated = $shield->decorateToken('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaabb');

    expect(Token::findByToken($fabricated))->toBeNull();
});

test('findByToken returns token for valid decorated token', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $found = Token::findByToken($token->plain);

    expect($found)->not->toBeNull()
        ->and($found->getKey())->toBe($token->getKey());
});

test('touchLastUsedAt updates when last_used_at is null', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    expect($token->last_used_at)->toBeNull();

    $token->touchLastUsedAt();
    $token->refresh();

    expect($token->last_used_at)->not->toBeNull();
});

test('touchLastUsedAt skips update within debounce window', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $token->touchLastUsedAt();
    $token->refresh();

    $firstTouch = $token->last_used_at;

    // Move forward 2 minutes — still within default 5-minute debounce
    $this->travel(2)->minutes();

    $token->touchLastUsedAt();
    $token->refresh();

    expect($token->last_used_at->timestamp)->toBe($firstTouch->timestamp);
});

test('touchLastUsedAt updates after debounce window expires', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $token->touchLastUsedAt();
    $token->refresh();

    $firstTouch = $token->last_used_at;

    // Move forward 6 minutes — beyond default 5-minute debounce
    $this->travel(6)->minutes();

    $token->touchLastUsedAt();
    $token->refresh();

    expect($token->last_used_at->timestamp)->toBeGreaterThan($firstTouch->timestamp);
});

test('prunable returns expired tokens older than pruneDays', function (): void {
    $user = User::factory()->create();

    $expired = Token::factory()->for($user, 'owner')->create([
        'expires_at' => Date::now()->subDays(31),
    ]);

    $prunableIds = Token::query()
        ->whereIn('id', (new Token)->prunable()->pluck('id'))
        ->pluck('id')
        ->all();

    expect($prunableIds)->toContain($expired->getKey());
});

test('prunable excludes tokens without expires_at', function (): void {
    $user = User::factory()->create();

    $noExpiry = Token::factory()->for($user, 'owner')->create([
        'expires_at' => null,
    ]);

    $prunableIds = (new Token)->prunable()->pluck('id')->all();

    expect($prunableIds)->not->toContain($noExpiry->getKey());
});

test('prunable excludes recently expired tokens within pruneDays', function (): void {
    $user = User::factory()->create();

    $recentlyExpired = Token::factory()->for($user, 'owner')->create([
        'expires_at' => Date::now()->subDays(5),
    ]);

    $prunableIds = (new Token)->prunable()->pluck('id')->all();

    expect($prunableIds)->not->toContain($recentlyExpired->getKey());
});

test('owner returns the associated user', function (): void {
    $user = User::factory()->create();
    $token = Token::factory()->for($user, 'owner')->create();

    $token->refresh();

    expect($token->owner)->not->toBeNull()
        ->and($token->owner->getKey())->toBe($user->getKey());
});
