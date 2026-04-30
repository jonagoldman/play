<?php

declare(strict_types=1);

use Deplox\Shield\Middlewares\AuthenticateSession;
use Deplox\Shield\Tests\Fixtures\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

function authSessionMiddleware(): AuthenticateSession
{
    return app(AuthenticateSession::class);
}

test('middleware passes through when request has no session', function (): void {
    $request = Request::create('/');

    $response = authSessionMiddleware()->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware passes through when no user is authenticated', function (): void {
    $request = Request::create('/');
    $request->setLaravelSession(session()->driver());

    $response = authSessionMiddleware()->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware passes through when no password_hash entry exists in session', function (): void {
    $user = User::factory()->create();
    $session = session()->driver();
    $session->start();

    $request = Request::create('/');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $user);

    $response = authSessionMiddleware()->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware passes through when stored password hash matches current', function (): void {
    $user = User::factory()->create();
    $session = session()->driver();
    $session->start();

    /** @var SessionGuard $guard */
    $guard = auth()->guard('session');

    $storedHash = method_exists($guard, 'hashPasswordForCookie')
        ? $guard->hashPasswordForCookie($user->getAuthPassword())
        : $user->getAuthPassword();

    $session->put('password_hash_session', $storedHash);

    $request = Request::create('/');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $user);

    $response = authSessionMiddleware()->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware re-stores password hash on response when guard has user', function (): void {
    $user = User::factory()->create();
    $session = session()->driver();
    $session->start();

    auth()->guard('session')->setUser($user);

    /** @var SessionGuard $guard */
    $guard = auth()->guard('session');
    $expected = method_exists($guard, 'hashPasswordForCookie')
        ? $guard->hashPasswordForCookie($user->getAuthPassword())
        : $user->getAuthPassword();

    $session->put('password_hash_session', $expected);

    $request = Request::create('/');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $user);

    authSessionMiddleware()->handle($request, fn () => new Response('ok'));

    expect($session->get('password_hash_session'))->toBe($expected);
});

test('middleware throws AuthenticationException when stored password hash mismatches', function (): void {
    $user = User::factory()->create();
    $session = session()->driver();
    $session->start();
    $session->put('password_hash_session', 'mismatched-hash-value');

    $request = Request::create('/');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $user);

    expect(fn () => authSessionMiddleware()->handle($request, fn () => new Response('ok')))
        ->toThrow(AuthenticationException::class);
});

test('middleware flushes session when password hash mismatches', function (): void {
    $user = User::factory()->create();
    $session = session()->driver();
    $session->start();
    $session->put('password_hash_session', 'mismatched-hash-value');
    $session->put('keep_me', 'should-be-flushed');

    $request = Request::create('/');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $user);

    try {
        authSessionMiddleware()->handle($request, fn () => new Response('ok'));
    } catch (AuthenticationException) {
        // expected
    }

    expect($session->get('keep_me'))->toBeNull()
        ->and($session->get('password_hash_session'))->toBeNull();
});

test('middleware accepts HMAC-format hash when guard supports it', function (): void {
    /** @var SessionGuard $guard */
    $guard = auth()->guard('session');

    if (! method_exists($guard, 'hashPasswordForCookie')) {
        $this->markTestSkipped('Laravel < 12.45.0 lacks hashPasswordForCookie support.');
    }

    $user = User::factory()->create();
    $session = session()->driver();
    $session->start();
    $session->put('password_hash_session', $guard->hashPasswordForCookie($user->getAuthPassword()));

    $request = Request::create('/');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $user);

    $response = authSessionMiddleware()->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware accepts raw password hash for backward compatibility', function (): void {
    $user = User::factory()->create();
    $session = session()->driver();
    $session->start();
    $session->put('password_hash_session', $user->getAuthPassword());

    $request = Request::create('/');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $user);

    $response = authSessionMiddleware()->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});
