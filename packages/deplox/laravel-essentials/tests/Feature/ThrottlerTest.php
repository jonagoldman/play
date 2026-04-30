<?php

declare(strict_types=1);

use Deplox\Essentials\Utilities\Throttler;
use Illuminate\Cache\RateLimiter;
use Illuminate\Container\Container;

beforeEach(function (): void {
    Container::getInstance()->make(RateLimiter::class)->clear('test-key');
});

test('attempt invokes the callback and returns its result', function (): void {
    $throttler = new Throttler('test-key', limit: 3, wait: 60);

    $result = $throttler->attempt(fn () => 'ok');

    expect($result)->toBe('ok');
});

test('attempt returns false when limit is exceeded', function (): void {
    $throttler = new Throttler('test-key', limit: 2, wait: 60);

    $throttler->attempt(fn () => 'a');
    $throttler->attempt(fn () => 'b');
    $third = $throttler->attempt(fn () => 'c');

    expect($third)->toBeFalse();
});

test('hit increments the counter', function (): void {
    $throttler = new Throttler('test-key', limit: 5, wait: 60);

    expect($throttler->hit())->toBe(1)
        ->and($throttler->hit())->toBe(2);
});

test('increment supports custom amounts', function (): void {
    $throttler = new Throttler('test-key', limit: 10, wait: 60);

    expect($throttler->increment(3))->toBe(3)
        ->and($throttler->increment(2))->toBe(5);
});

test('decrement supports custom amounts', function (): void {
    $throttler = new Throttler('test-key', limit: 10, wait: 60);

    $throttler->increment(5);

    expect($throttler->decrement(2))->toBe(3);
});

test('attempts returns the current count', function (): void {
    $throttler = new Throttler('test-key', limit: 5, wait: 60);

    $throttler->hit();
    $throttler->hit();

    expect($throttler->attempts())->toBe(2);
});

test('remaining reports retries left', function (): void {
    $throttler = new Throttler('test-key', limit: 3, wait: 60);

    $throttler->hit();

    expect($throttler->remaining())->toBe(2);
});

test('tooManyAttempts toggles past the limit', function (): void {
    $throttler = new Throttler('test-key', limit: 2, wait: 60);

    expect($throttler->tooManyAttempts())->toBeFalse();

    $throttler->hit();
    $throttler->hit();

    expect($throttler->tooManyAttempts())->toBeTrue();
});

test('clear resets the counter', function (): void {
    $throttler = new Throttler('test-key', limit: 5, wait: 60);

    $throttler->hit();
    $throttler->hit();
    $throttler->clear();

    expect($throttler->attempts())->toBe(0);
});

test('resetAttempts wipes the counter', function (): void {
    $throttler = new Throttler('test-key', limit: 5, wait: 60);

    $throttler->hit();
    $throttler->resetAttempts();

    expect($throttler->attempts())->toBe(0);
});
