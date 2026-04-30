<?php

declare(strict_types=1);

use Deplox\Support\Tests\Fixtures\Post;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('posts', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->ulid('author_id')->nullable();
        $table->timestamp('expires_at')->nullable();
    });
});

test('expires() sets expires_at attribute', function (): void {
    $expires = now()->addDay();

    $post = (new Post(['name' => 'A']))->expires($expires);

    expect($post->expires_at?->getTimestamp())->toBe($expires->getTimestamp());
});

test('addMonths/addWeeks/addDays/addHours/addMinutes chain from current expiration', function (): void {
    $base = now()->startOfDay();
    $post = (new Post(['name' => 'A']))->expires($base);

    expect($post->addMonths(1)->expires_at->getTimestamp())->toBe($base->copy()->addMonths(1)->getTimestamp())
        ->and($post->expires(clone $base)->addWeeks(2)->expires_at->getTimestamp())->toBe($base->copy()->addWeeks(2)->getTimestamp())
        ->and($post->expires(clone $base)->addDays(3)->expires_at->getTimestamp())->toBe($base->copy()->addDays(3)->getTimestamp())
        ->and($post->expires(clone $base)->addHours(4)->expires_at->getTimestamp())->toBe($base->copy()->addHours(4)->getTimestamp())
        ->and($post->expires(clone $base)->addMinutes(5)->expires_at->getTimestamp())->toBe($base->copy()->addMinutes(5)->getTimestamp());
});

test('duration helpers default to now() when expires_at is null', function (): void {
    $now = now();
    $this->travelTo($now);

    $post = new Post(['name' => 'A']);

    expect($post->expires_at)->toBeNull()
        ->and($post->addDays(1)->expires_at->getTimestamp())->toBe($now->copy()->addDay()->getTimestamp());
});

test('whereExpired scope returns only expired records', function (): void {
    Post::create(['name' => 'past', 'expires_at' => now()->subDay()]);
    Post::create(['name' => 'future', 'expires_at' => now()->addDay()]);
    Post::create(['name' => 'no-expiry']);

    expect(Post::query()->whereExpired()->pluck('name')->all())->toBe(['past']);
});

test('whereNotExpired scope returns null and future records', function (): void {
    Post::create(['name' => 'past', 'expires_at' => now()->subDay()]);
    Post::create(['name' => 'future', 'expires_at' => now()->addDay()]);
    Post::create(['name' => 'no-expiry']);

    expect(Post::query()->whereNotExpired()->pluck('name')->sort()->values()->all())
        ->toBe(['future', 'no-expiry']);
});

test('expired accessor reads true when expires_at is past', function (): void {
    $post = new Post(['name' => 'p']);
    $post->expires_at = now()->subSecond();

    expect($post->expired)->toBeTrue();
});

test('expired accessor reads false when expires_at is null or future', function (): void {
    $post = new Post(['name' => 'p']);

    expect($post->expired)->toBeFalse();

    $post->expires_at = now()->addSecond();

    expect($post->expired)->toBeFalse();
});

test('expired mutator true sets expires_at to current time', function (): void {
    $post = new Post(['name' => 'p', 'expired' => true]);

    expect($post->expires_at)->not->toBeNull()
        ->and($post->expires_at->getTimestamp())->toBeLessThanOrEqual(now()->getTimestamp());
});

test('expired mutator false clears expires_at', function (): void {
    $post = new Post(['name' => 'p']);
    $post->expires_at = now()->addDay();
    $post->expired = false;

    expect($post->expires_at)->toBeNull();
});
