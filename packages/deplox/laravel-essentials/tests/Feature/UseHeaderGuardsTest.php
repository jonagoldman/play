<?php

declare(strict_types=1);

use Deplox\Essentials\Middlewares\UseHeaderGuards;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

beforeEach(function (): void {
    $this->middleware = new UseHeaderGuards;
});

test('sets X-Frame-Options to SAMEORIGIN', function (): void {
    $response = $this->middleware->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
});

test('sets X-Content-Type-Options to nosniff', function (): void {
    $response = $this->middleware->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('sets a one-year HSTS header with includeSubDomains and preload', function (): void {
    $response = $this->middleware->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains; preload');
});

test('preserves the response body and status', function (): void {
    $response = $this->middleware->handle(
        Request::create('/'),
        fn () => new Response('hello', 201),
    );

    expect($response->getContent())->toBe('hello')
        ->and($response->getStatusCode())->toBe(201);
});
