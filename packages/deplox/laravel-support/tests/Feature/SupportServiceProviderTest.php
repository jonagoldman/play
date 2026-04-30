<?php

declare(strict_types=1);

test('support translation namespace resolves the exists_model key', function (): void {
    expect(__('support::validation.exists_model', ['attribute' => 'id']))
        ->toBe('A resource with this id does not exist.');
});

test('support translation namespace resolves the unique_model key', function (): void {
    expect(__('support::validation.unique_model', ['attribute' => 'email']))
        ->toBe('A resource with this email already exists.');
});

test('translations are publishable under the laravel-support-translations tag', function (): void {
    $paths = Illuminate\Support\ServiceProvider::pathsToPublish(
        Deplox\Support\SupportServiceProvider::class,
        'laravel-support-translations',
    );

    expect($paths)->not->toBeEmpty();
});
