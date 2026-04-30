<?php

declare(strict_types=1);

use Deplox\Support\Tests\Fixtures\Animal;
use Deplox\Support\Tests\Fixtures\Cat;
use Deplox\Support\Tests\Fixtures\Dog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('animals', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('type');
    });
});

test('newFromBuilder hydrates the correct child class based on type column', function (): void {
    Dog::create(['name' => 'Rex']);
    Cat::create(['name' => 'Whiskers']);

    $animals = Animal::query()->orderBy('name')->get();

    expect($animals->first(fn ($a) => $a->name === 'Rex'))->toBeInstanceOf(Dog::class)
        ->and($animals->first(fn ($a) => $a->name === 'Whiskers'))->toBeInstanceOf(Cat::class);
});

test('classFromAlias resolves alias to fully qualified class name', function (): void {
    $animal = new Animal;

    expect($animal->classFromAlias('dog'))->toBe(Dog::class)
        ->and($animal->classFromAlias('cat'))->toBe(Cat::class);
});

test('classFromAlias returns input unchanged when not in childTypes map', function (): void {
    $animal = new Animal;

    expect($animal->classFromAlias('unknown'))->toBe('unknown');
});

test('classToAlias resolves class name to alias', function (): void {
    $animal = new Animal;

    expect($animal->classToAlias(Dog::class))->toBe('dog')
        ->and($animal->classToAlias(Cat::class))->toBe('cat');
});

test('getInheritanceColumn returns "type" by default', function (): void {
    expect((new Animal)->getInheritanceColumn())->toBe('type');
});

test('getChildTypes returns the configured map', function (): void {
    expect((new Animal)->getChildTypes())->toBe([
        'dog' => Dog::class,
        'cat' => Cat::class,
    ]);
});

test('Dog instances are filtered by global scope when queried via Dog', function (): void {
    Dog::create(['name' => 'Rex']);
    Cat::create(['name' => 'Whiskers']);

    $dogs = Dog::query()->get();

    expect($dogs)->toHaveCount(1)
        ->and($dogs->first()->name)->toBe('Rex');
});

test('children inherit the type column from their alias on creation', function (): void {
    $dog = Dog::create(['name' => 'Rex']);

    expect($dog->fresh()->type)->toBe('dog');
});

test('parentIsBooting returns false after parent has fully booted', function (): void {
    // Force boot of Animal by accessing it.
    Animal::query()->count();

    $reflection = new ReflectionMethod(Animal::class, 'parentIsBooting');

    expect($reflection->invoke(null))->toBeFalse();
});
