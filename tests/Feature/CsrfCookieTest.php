<?php

declare(strict_types=1);

test('stateful request returns 204 with XSRF-TOKEN cookie', function (): void {
    $response = $this->get('/auth/csrf-cookie', [
        'Origin' => 'https://play.ddev.site',
    ]);

    $response->assertNoContent();

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN');

    expect($cookie)->not->toBeNull();
});

test('non-stateful request returns 204 without XSRF-TOKEN cookie', function (): void {
    $response = $this->get('/auth/csrf-cookie');

    $response->assertNoContent();

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN');

    expect($cookie)->toBeNull();
});
