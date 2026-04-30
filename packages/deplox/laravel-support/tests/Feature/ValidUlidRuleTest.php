<?php

declare(strict_types=1);

use Deplox\Support\Validation\Rules\ValidUlid;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

test('accepts a freshly generated ULID', function (): void {
    $validator = Validator::make(
        ['id' => (string) Str::ulid()],
        ['id' => [new ValidUlid]],
    );

    expect($validator->fails())->toBeFalse();
});

test('rejects a UUID', function (): void {
    $validator = Validator::make(
        ['id' => '01234567-89ab-cdef-0123-456789abcdef'],
        ['id' => [new ValidUlid]],
    );

    expect($validator->fails())->toBeTrue();
});

test('rejects an empty string when paired with required', function (): void {
    // Laravel skips non-implicit rules for empty values by default;
    // combine with `required` to enforce presence.
    $validator = Validator::make(
        ['id' => ''],
        ['id' => ['required', new ValidUlid]],
    );

    expect($validator->fails())->toBeTrue();
});

test('rejects a non-string value', function (): void {
    $validator = Validator::make(
        ['id' => 12345],
        ['id' => [new ValidUlid]],
    );

    expect($validator->fails())->toBeTrue();
});

test('rejects a string that contains an I or L (Crockford excludes these)', function (): void {
    $validator = Validator::make(
        ['id' => 'IIIIIIIIIIIIIIIIIIIIIIIIII'],
        ['id' => [new ValidUlid]],
    );

    expect($validator->fails())->toBeTrue();
});
