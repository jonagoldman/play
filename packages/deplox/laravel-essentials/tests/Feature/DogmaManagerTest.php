<?php

declare(strict_types=1);

use Deplox\Essentials\Dogma\DogmaManager;
use Deplox\Essentials\EssentialsConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Builder;

function dogmaConfig(array $overrides = []): EssentialsConfig
{
    return EssentialsConfig::fromArray(array_merge([
        'fake_sleep' => true,
        'prevent_stray_requests' => true,
        'force_https' => false,
        'aggressive_prefetching' => false,
        'immutable_dates' => true,
        'unguard_model' => false,
        'strict_model' => true,
        'automatic_eager_load_relationships' => true,
        'require_morph_map' => true,
        'prohibit_destructive_commands' => true,
        'set_default_passwords' => true,
        'default_string_length' => 255,
        'default_morph_key_type' => 'int',
    ], $overrides));
}

test('apply propagates each principle', function (): void {
    $manager = new DogmaManager(dogmaConfig([
        'unguard_model' => true,
        'require_morph_map' => true,
        'default_string_length' => 100,
    ]));

    $manager->apply();

    expect(Model::isUnguarded())->toBeTrue()
        ->and(Relation::requiresMorphMap())->toBeTrue()
        ->and(Builder::$defaultStringLength)->toBe(100);

    Model::unguard(false);
});

test('status returns the four principle keys', function (): void {
    $status = new DogmaManager(dogmaConfig())->status();

    expect($status)->toHaveKeys(['http', 'model', 'database', 'general'])
        ->and($status['http'])->toBeArray()
        ->and($status['model'])->toBeArray()
        ->and($status['database'])->toBeArray()
        ->and($status['general'])->toBeArray();
});

test('status reports require_morph_map after apply()', function (): void {
    Relation::requireMorphMap(false);

    $manager = new DogmaManager(dogmaConfig(['require_morph_map' => true]));
    $manager->apply();

    expect($manager->status()['model']['requireMorphMap'])->toBeTrue();
});
