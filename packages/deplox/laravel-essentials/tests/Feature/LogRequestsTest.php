<?php

declare(strict_types=1);

use Deplox\Essentials\Middlewares\LogRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

test('logs an info entry with method, path, status, and duration', function (): void {
    Log::spy();

    $request = Request::create('/example/path', 'POST');

    new LogRequests()->handle($request, fn () => new Response('ok', 201));

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'request'
                && $context['method'] === 'POST'
                && $context['path'] === 'example/path'
                && $context['status'] === 201
                && is_int($context['duration_ms']);
        });
});

test('returns the response unchanged', function (): void {
    Log::spy();

    $response = new LogRequests()->handle(
        Request::create('/'),
        fn () => new Response('hello', 200),
    );

    expect($response->getContent())->toBe('hello')
        ->and($response->getStatusCode())->toBe(200);
});
