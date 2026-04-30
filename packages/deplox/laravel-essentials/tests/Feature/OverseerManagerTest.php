<?php

declare(strict_types=1);

use Deplox\Essentials\Overseer\OverseerManager;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->overseer = new OverseerManager($this->app);
});

test('inspect returns a collection with the expected top-level keys', function (): void {
    $result = $this->overseer->inspect();

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->keys()->all())->toBe([
            'environment',
            'providers',
            'aliases',
            'bindings',
            'instances',
            'extenders',
            'router',
        ]);
});

test('environment returns PHP and framework version metadata', function (): void {
    $env = $this->overseer->environment();

    expect($env)->toBeArray()
        ->and($env)->not->toBeEmpty();
});

test('providers returns a non-empty array of registered service providers', function (): void {
    $providers = $this->overseer->providers();

    expect($providers)->toBeArray()->not->toBeEmpty();
});

test('bindings returns the application container bindings', function (): void {
    $bindings = $this->overseer->bindings();

    expect($bindings)->toBeArray()->not->toBeEmpty();
});

test('aliases returns container aliases', function (): void {
    $aliases = $this->overseer->aliases();

    expect($aliases)->toBeArray();
});

test('instances returns container resolved instances', function (): void {
    $instances = $this->overseer->instances();

    expect($instances)->toBeArray()->not->toBeEmpty();
});

test('extenders returns container extenders', function (): void {
    $extenders = $this->overseer->extenders();

    expect($extenders)->toBeArray();
});

test('router returns at least one registered route', function (): void {
    $this->app['router']->get('/__overseer-probe', fn () => 'ok');

    $routes = $this->overseer->router();

    expect($routes)->toBeArray()->not->toBeEmpty();
});

test('toArray serializes inspect() output to nested arrays', function (): void {
    $array = $this->overseer->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveKey('environment')
        ->and($array)->toHaveKey('router');
});

test('router inspector replaces closure actions with the string "closure"', function (): void {
    $this->app['router']->get('/__overseer-closure-route', fn () => 'ok');

    $routes = $this->overseer->router()['routes'] ?? [];

    $found = false;

    array_walk_recursive($routes, function (mixed $value, mixed $key) use (&$found): void {
        if ($key === 'uses' && $value === 'closure') {
            $found = true;
        }
    });

    expect($found)->toBeTrue();
});

test('router inspector output is JSON-encodable', function (): void {
    $this->app['router']->get('/__overseer-json-route', fn () => 'ok');

    $json = json_encode($this->overseer->router(), JSON_THROW_ON_ERROR);

    expect($json)->toBeString();
});
