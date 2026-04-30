<?php

declare(strict_types=1);

use Deplox\Essentials\Middlewares\UseRequestId;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;

beforeEach(function (): void {
    $this->middleware = new UseRequestId;
});

test('generates a ULID when no incoming X-REQUEST-ID header is present', function (): void {
    $request = Request::create('/');

    $response = $this->middleware->handle($request, fn () => new Response('ok'));

    $requestId = $response->headers->get('X-REQUEST-ID');

    expect($requestId)->toBeString()
        ->and(mb_strlen($requestId))->toBe(26);
});

test('propagates an incoming X-REQUEST-ID header', function (): void {
    $request = Request::create('/');
    $request->headers->set('X-REQUEST-ID', 'incoming-id-123');

    $response = $this->middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-REQUEST-ID'))->toBe('incoming-id-123');
});

test('stores the request id in Context for the duration of the request', function (): void {
    $request = Request::create('/');
    $request->headers->set('X-REQUEST-ID', 'ctx-id-456');

    $captured = null;
    $this->middleware->handle($request, function () use (&$captured): Response {
        $captured = Context::get('requestId');

        return new Response('ok');
    });

    expect($captured)->toBe('ctx-id-456');
});

test('echoes back the generated id when no header was provided', function (): void {
    $request = Request::create('/');

    $response = $this->middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-REQUEST-ID'))
        ->toBe(Context::get('requestId'));
});
