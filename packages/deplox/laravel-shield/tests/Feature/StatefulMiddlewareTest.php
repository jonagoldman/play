<?php

declare(strict_types=1);

use Deplox\Shield\Middlewares\AuthenticateSession;
use Deplox\Shield\Middlewares\StatefulFrontend;
use Deplox\Shield\Shield;
use Deplox\Shield\Tests\Fixtures\Token;
use Deplox\Shield\Tests\Fixtures\User;

test('auth config includes default middleware', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
    );

    expect($shield->middlewares)->toBe([
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        'authenticate_session' => AuthenticateSession::class,
    ]);
});

test('auth config allows overriding a single middleware', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        middlewares: [
            'encrypt_cookies' => 'App\Http\Middleware\CustomEncryptCookies',
            'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'authenticate_session' => AuthenticateSession::class,
        ],
    );

    expect($shield->middlewares['encrypt_cookies'])->toBe('App\Http\Middleware\CustomEncryptCookies')
        ->and($shield->middlewares['validate_csrf_token'])->toBe(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->and($shield->middlewares['authenticate_session'])->toBe(AuthenticateSession::class);
});

test('auth config allows removing middleware via null', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        middlewares: [
            'encrypt_cookies' => null,
            'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'authenticate_session' => AuthenticateSession::class,
        ],
    );

    expect($shield->middlewares['encrypt_cookies'])->toBeNull()
        ->and($shield->middlewares['validate_csrf_token'])->toBe(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->and($shield->middlewares['authenticate_session'])->toBe(AuthenticateSession::class);
});

test('auth config allows overriding multiple middleware at once', function (): void {
    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        middlewares: [
            'encrypt_cookies' => null,
            'validate_csrf_token' => 'App\Http\Middleware\CustomCsrf',
            'authenticate_session' => AuthenticateSession::class,
        ],
    );

    expect($shield->middlewares['encrypt_cookies'])->toBeNull()
        ->and($shield->middlewares['validate_csrf_token'])->toBe('App\Http\Middleware\CustomCsrf')
        ->and($shield->middlewares['authenticate_session'])->toBe(AuthenticateSession::class);
});

test('fromFrontend rejects subdomain when stateful_subdomains is false', function (): void {
    config([
        'shield.stateful' => ['example.com'],
        'shield.stateful_subdomains' => false,
    ]);

    $middleware = new StatefulFrontend(app(Shield::class));

    $request = Illuminate\Http\Request::create('/');
    $request->headers->set('origin', 'https://evil.example.com');

    expect($middleware->fromFrontend($request))->toBeFalse();
});

test('fromFrontend accepts subdomain when stateful_subdomains is true', function (): void {
    config([
        'shield.stateful' => ['example.com'],
        'shield.stateful_subdomains' => true,
    ]);

    $middleware = new StatefulFrontend(app(Shield::class));

    $request = Illuminate\Http\Request::create('/');
    $request->headers->set('origin', 'https://admin.example.com');

    expect($middleware->fromFrontend($request))->toBeTrue();
});

test('fromFrontend rejects request with mismatched port', function (): void {
    config(['shield.stateful' => ['localhost:3000']]);

    $middleware = new StatefulFrontend(app(Shield::class));

    $request = Illuminate\Http\Request::create('/');
    $request->headers->set('origin', 'https://localhost:9999');

    expect($middleware->fromFrontend($request))->toBeFalse();
});

test('fromFrontend accepts whitelisted domain with correct port', function (): void {
    config(['shield.stateful' => ['localhost:3000']]);

    $middleware = new StatefulFrontend(app(Shield::class));

    $request = Illuminate\Http\Request::create('/');
    $request->headers->set('origin', 'https://localhost:3000');

    expect($middleware->fromFrontend($request))->toBeTrue();
});

test('fromFrontend accepts whitelisted domain without port', function (): void {
    config(['shield.stateful' => ['example.com']]);

    $middleware = new StatefulFrontend(app(Shield::class));

    $request = Illuminate\Http\Request::create('/');
    $request->headers->set('origin', 'https://example.com');

    expect($middleware->fromFrontend($request))->toBeTrue();
});

test('fromFrontend rejects request with no origin or referer', function (): void {
    $middleware = new StatefulFrontend(app(Shield::class));

    $request = Illuminate\Http\Request::create('/');

    expect($middleware->fromFrontend($request))->toBeFalse();
});

test('explicit statefulDomains array overrides config', function (): void {
    config(['shield.stateful' => ['should-not-match.com']]);

    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
        statefulDomains: ['explicit.example.com'],
    );

    $middleware = new StatefulFrontend($shield);

    $request = Illuminate\Http\Request::create('/');
    $request->headers->set('origin', 'https://explicit.example.com');

    expect($middleware->fromFrontend($request))->toBeTrue();

    $request->headers->set('origin', 'https://should-not-match.com');

    expect($middleware->fromFrontend($request))->toBeFalse();
});

test('null statefulDomains reads from config', function (): void {
    config(['shield.stateful' => ['config-domain.example.com']]);

    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
    );

    expect($shield->statefulDomains)->toBeNull()
        ->and($shield->statefulDomains())->toContain('config-domain.example.com');
});

test('statefulDomains() filters empty values from config', function (): void {
    config(['shield.stateful' => ['valid.com', '', '  ', 'also-valid.com']]);

    $shield = new Shield(
        tokenModel: Token::class,
        userModel: User::class,
    );

    expect($shield->statefulDomains())->toBe(['valid.com', 'also-valid.com']);
});

test('currentApplicationUrlWithPort extracts host from APP_URL', function (): void {
    config(['app.url' => 'https://example.com']);

    expect(Shield::currentApplicationUrlWithPort())->toBe('example.com');
});

test('currentApplicationUrlWithPort includes port when present', function (): void {
    config(['app.url' => 'https://localhost:8080']);

    expect(Shield::currentApplicationUrlWithPort())->toBe('localhost:8080');
});

test('currentApplicationUrlWithPort returns empty string when no APP_URL', function (): void {
    config(['app.url' => null]);

    expect(Shield::currentApplicationUrlWithPort())->toBe('');
});

test('currentApplicationUrlWithPort returns empty string for invalid URL', function (): void {
    config(['app.url' => '']);

    expect(Shield::currentApplicationUrlWithPort())->toBe('');
});
