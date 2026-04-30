<?php

declare(strict_types=1);

use Deplox\Support\Validation\Rules\StrongPassword;
use Illuminate\Support\Facades\Validator;

test('moderate accepts an 8-char letters+numbers password', function (): void {
    $validator = Validator::make(
        ['password' => 'abc12345'],
        ['password' => [StrongPassword::moderate()]],
    );

    expect($validator->fails())->toBeFalse();
});

test('moderate rejects a too-short password', function (): void {
    $validator = Validator::make(
        ['password' => 'abc12'],
        ['password' => [StrongPassword::moderate()]],
    );

    expect($validator->fails())->toBeTrue();
});

test('moderate rejects a password with no letters', function (): void {
    $validator = Validator::make(
        ['password' => '12345678'],
        ['password' => [StrongPassword::moderate()]],
    );

    expect($validator->fails())->toBeTrue();
});

test('default rejects an all-lowercase password', function (): void {
    $validator = Validator::make(
        ['password' => 'abcdefghijkl'],
        ['password' => [StrongPassword::default()]],
    );

    expect($validator->fails())->toBeTrue();
});

test('default rejects a password missing symbols', function (): void {
    $validator = Validator::make(
        ['password' => 'AbcDef123456'],
        ['password' => [StrongPassword::default()]],
    );

    expect($validator->fails())->toBeTrue();
});
