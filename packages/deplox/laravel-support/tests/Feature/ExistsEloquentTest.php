<?php

declare(strict_types=1);

use Deplox\Support\Tests\Fixtures\Author;
use Deplox\Support\Validation\Rules\ExistsEloquent;
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

test('passes when value exists by primary key', function (): void {
    $author = Author::create(['name' => 'Ada']);

    $validator = Validator::make(
        ['author_id' => $author->id],
        ['author_id' => [new ExistsEloquent(Author::class)]],
    );

    expect($validator->fails())->toBeFalse();
});

test('fails when value does not exist', function (): void {
    $validator = Validator::make(
        ['author_id' => '01234567890123456789012345'],
        ['author_id' => [new ExistsEloquent(Author::class)]],
    );

    expect($validator->fails())->toBeTrue();
});

test('passes when value exists on a non-primary column', function (): void {
    Author::create(['name' => 'Ada']);

    $validator = Validator::make(
        ['author_name' => 'Ada'],
        ['author_name' => [new ExistsEloquent(Author::class, 'name')]],
    );

    expect($validator->fails())->toBeFalse();
});

test('builder closure narrows the existence query', function (): void {
    Author::create(['name' => 'Ada']);
    Author::create(['name' => 'Babbage']);

    $rule = new ExistsEloquent(
        Author::class,
        'name',
        fn (Builder $q): Builder => $q->where('name', 'Ada'),
    );

    expect(Validator::make(['n' => 'Ada'], ['n' => [$rule]])->fails())->toBeFalse()
        ->and(Validator::make(['n' => 'Babbage'], ['n' => [$rule]])->fails())->toBeTrue();
});

test('withMessage replaces the default failure message', function (): void {
    $rule = (new ExistsEloquent(Author::class))->withMessage('custom failure');

    $validator = Validator::make(['x' => 'missing'], ['x' => [$rule]]);

    $validator->fails();

    expect($validator->errors()->first('x'))->toBe('custom failure');
});

test('query() chains a builder closure fluently', function (): void {
    Author::create(['name' => 'Ada']);

    $rule = (new ExistsEloquent(Author::class, 'name'))
        ->query(fn (Builder $q): Builder => $q->where('name', 'Ada'));

    expect(Validator::make(['n' => 'Ada'], ['n' => [$rule]])->fails())->toBeFalse();
});
