<?php

declare(strict_types=1);

use Deplox\Support\Tests\Fixtures\Country;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

afterEach(function (): void {
    $cacheFile = storage_path('framework/cache/sushi/'.Str::kebab(str_replace('\\', '', Country::class)).'.sqlite');

    if (File::exists($cacheFile)) {
        File::delete($cacheFile);
    }
});

test('rows are queryable from the in-memory connection', function (): void {
    $countries = Country::all();

    expect($countries)->toHaveCount(3)
        ->and($countries->pluck('code')->all())->toBe(['US', 'GB', 'FR']);
});

test('individual records are retrievable by primary key', function (): void {
    $country = Country::find(2);

    expect($country?->code)->toBe('GB')
        ->and($country?->name)->toBe('United Kingdom');
});

test('where queries work against the cached SQLite file', function (): void {
    expect(Country::where('code', 'FR')->first()->name)->toBe('France');
});

test('cache file is created in the sushi directory on first boot', function (): void {
    Country::all();

    $cacheFile = storage_path('framework/cache/sushi/'.Str::kebab(str_replace('\\', '', Country::class)).'.sqlite');

    expect(File::exists($cacheFile))->toBeTrue();
});

test('getRows returns the configured rows array', function (): void {
    expect((new Country)->getRows())->toHaveCount(3);
});

test('getSchema returns empty array when not configured', function (): void {
    expect((new Country)->getSchema())->toBe([]);
});

test('connection name matches the model class', function (): void {
    expect((new Country)->getConnectionName())->toBe(Country::class);
});
