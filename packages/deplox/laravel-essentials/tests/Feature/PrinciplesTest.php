<?php

declare(strict_types=1);

use Deplox\Essentials\Dogma\Principles\DatabasePrinciple;
use Deplox\Essentials\Dogma\Principles\HttpPrinciple;
use Deplox\Essentials\Dogma\Principles\ModelPrinciple;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

test('HttpPrinciple status reports configured flags as concrete booleans', function (): void {
    $status = HttpPrinciple::status();

    expect($status)
        ->toHaveKeys(['fakeSleep', 'forceHttps', 'aggressivePrefetching', 'preventStrayRequests'])
        ->and($status['aggressivePrefetching'])->toBeBool()
        ->and($status['preventStrayRequests'])->toBeBool()
        ->and($status['forceHttps'])->toBeBool();
});

test('HttpPrinciple reflects fakeSleep state from Sleep facade', function (): void {
    Sleep::fake();

    expect(HttpPrinciple::status()['fakeSleep'])->toBeTrue();
});

test('HttpPrinciple reflects preventStrayRequests state from Http facade', function (): void {
    Http::preventStrayRequests();

    expect(HttpPrinciple::status()['preventStrayRequests'])->toBeTrue();

    Http::preventStrayRequests(false);

    expect(HttpPrinciple::status()['preventStrayRequests'])->toBeFalse();
});

test('DatabasePrinciple status returns concrete values', function (): void {
    $status = DatabasePrinciple::status();

    expect($status)
        ->toHaveKeys(['defaultStringLength', 'defaultMorphKeyType', 'prohibitsDestructiveCommands'])
        ->and($status['prohibitsDestructiveCommands'])->toBeBool()
        ->and($status['defaultMorphKeyType'])->toBeString()
        ->and($status['defaultStringLength'])->toBeInt();
});

test('DatabasePrinciple defaultStringLength reflects Builder static', function (): void {
    Builder::defaultStringLength(123);

    expect(DatabasePrinciple::status()['defaultStringLength'])->toBe(123);

    Builder::defaultStringLength(255);
});

test('ModelPrinciple status reports requireMorphMap as a boolean', function (): void {
    $status = ModelPrinciple::status();

    expect($status)
        ->toHaveKey('requireMorphMap')
        ->and($status['requireMorphMap'])->toBeBool()
        ->and($status['unguarded'])->toBeBool()
        ->and($status['preventsLazyLoading'])->toBeBool();
});

test('ModelPrinciple reflects unguarded state', function (): void {
    Model::unguard();

    expect(ModelPrinciple::status()['unguarded'])->toBeTrue();

    Model::unguard(false);

    expect(ModelPrinciple::status()['unguarded'])->toBeFalse();
});

test('ModelPrinciple reflects requireMorphMap toggle', function (): void {
    Relation::requireMorphMap();

    expect(ModelPrinciple::status()['requireMorphMap'])->toBeTrue();

    Relation::requireMorphMap(false);

    expect(ModelPrinciple::status()['requireMorphMap'])->toBeFalse();
});
