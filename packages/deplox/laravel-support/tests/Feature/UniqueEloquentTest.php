<?php

declare(strict_types=1);

use Deplox\Support\Tests\Fixtures\Author;
use Deplox\Support\Validation\Rules\UniqueEloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    Schema::create('authors', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
    });
});

test('passes when value is not yet stored', function (): void {
    $validator = Validator::make(
        ['name' => 'Ada'],
        ['name' => [new UniqueEloquent(Author::class, 'name')]],
    );

    expect($validator->fails())->toBeFalse();
});

test('fails when value already exists', function (): void {
    Author::create(['name' => 'Ada']);

    $validator = Validator::make(
        ['name' => 'Ada'],
        ['name' => [new UniqueEloquent(Author::class, 'name')]],
    );

    expect($validator->fails())->toBeTrue();
});

test('ignore() excludes a record from the uniqueness check', function (): void {
    $existing = Author::create(['name' => 'Ada']);

    $rule = (new UniqueEloquent(Author::class, 'name'))->ignore($existing->id);

    $validator = Validator::make(
        ['name' => 'Ada'],
        ['name' => [$rule]],
    );

    expect($validator->fails())->toBeFalse();
});

test('ignore() with custom column ignores by that column', function (): void {
    $existing = Author::create(['name' => 'Ada']);

    $rule = (new UniqueEloquent(Author::class, 'name'))->ignore('Ada', 'name');

    $validator = Validator::make(
        ['name' => 'Ada'],
        ['name' => [$rule]],
    );

    expect($validator->fails())->toBeFalse()
        ->and($existing->name)->toBe('Ada');
});

test('builder closure narrows the uniqueness query', function (): void {
    Author::create(['name' => 'Ada']);

    $rule = new UniqueEloquent(
        Author::class,
        'name',
        fn (Builder $q): Builder => $q->where('name', '!=', 'Ada'),
    );

    expect(Validator::make(['name' => 'Ada'], ['name' => [$rule]])->fails())->toBeFalse();
});

test('withMessage replaces the default failure message', function (): void {
    Author::create(['name' => 'Ada']);

    $rule = (new UniqueEloquent(Author::class, 'name'))->withMessage('taken');

    $validator = Validator::make(['name' => 'Ada'], ['name' => [$rule]]);

    $validator->fails();

    expect($validator->errors()->first('name'))->toBe('taken');
});
