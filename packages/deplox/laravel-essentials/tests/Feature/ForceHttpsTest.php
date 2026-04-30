<?php

declare(strict_types=1);

use Deplox\Essentials\Middlewares\ForceHttps;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

test('passes through HTTPS requests', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $request = Request::create('https://example.com/foo');

    $response = new ForceHttps()->handle($request, fn () => new Response('ok'));

    expect($response)->not->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getContent())->toBe('ok');
});

test('redirects HTTP requests in production to HTTPS', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $request = Request::create('http://example.com/foo?bar=baz');

    $response = new ForceHttps()->handle($request, fn () => new Response('ok'));

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getStatusCode())->toBe(301)
        ->and($response->getTargetUrl())->toBe('https://example.com/foo?bar=baz');
});

test('does not redirect when not in production', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    $request = Request::create('http://example.com/foo');

    $response = new ForceHttps()->handle($request, fn () => new Response('ok'));

    expect($response)->not->toBeInstanceOf(RedirectResponse::class);
});
