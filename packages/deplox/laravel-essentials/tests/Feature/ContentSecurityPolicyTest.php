<?php

declare(strict_types=1);

use Deplox\Essentials\Middlewares\ContentSecurityPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('builds a CSP header from config directives', function (): void {
    config(['essentials.csp' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", 'https://cdn.example.com'],
    ]]);

    $response = new ContentSecurityPolicy()->handle(
        Request::create('/'),
        fn () => new Response('ok'),
    );

    expect($response->headers->get('Content-Security-Policy'))
        ->toBe("default-src 'self'; script-src 'self' https://cdn.example.com");
});

test('is a no-op when config is empty', function (): void {
    config(['essentials.csp' => []]);

    $response = new ContentSecurityPolicy()->handle(
        Request::create('/'),
        fn () => new Response('ok'),
    );

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
});

test('is a no-op when config is missing', function (): void {
    config(['essentials.csp' => null]);

    $response = new ContentSecurityPolicy()->handle(
        Request::create('/'),
        fn () => new Response('ok'),
    );

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
});

test('preserves the response body and status', function (): void {
    config(['essentials.csp' => ['default-src' => ["'self'"]]]);

    $response = new ContentSecurityPolicy()->handle(
        Request::create('/'),
        fn () => new Response('hello', 201),
    );

    expect($response->getContent())->toBe('hello')
        ->and($response->getStatusCode())->toBe(201);
});
