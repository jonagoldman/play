<?php

declare(strict_types=1);

use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\Middlewares\AuthenticateSession;
use JonaGoldman\Auth\Tests\Fixtures\Token;
use JonaGoldman\Auth\Tests\Fixtures\User;

test('auth config includes default middleware', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
    );

    expect($config->middlewares)->toBe([
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        'authenticate_session' => AuthenticateSession::class,
    ]);
});

test('auth config allows overriding a single middleware', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        middlewares: [
            'encrypt_cookies' => 'App\Http\Middleware\CustomEncryptCookies',
            'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'authenticate_session' => AuthenticateSession::class,
        ],
    );

    expect($config->middlewares['encrypt_cookies'])->toBe('App\Http\Middleware\CustomEncryptCookies')
        ->and($config->middlewares['validate_csrf_token'])->toBe(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->and($config->middlewares['authenticate_session'])->toBe(AuthenticateSession::class);
});

test('auth config allows removing middleware via null', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        middlewares: [
            'encrypt_cookies' => null,
            'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'authenticate_session' => AuthenticateSession::class,
        ],
    );

    expect($config->middlewares['encrypt_cookies'])->toBeNull()
        ->and($config->middlewares['validate_csrf_token'])->toBe(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->and($config->middlewares['authenticate_session'])->toBe(AuthenticateSession::class);
});

test('auth config allows overriding multiple middleware at once', function (): void {
    $config = new AuthConfig(
        tokenModel: Token::class,
        userModel: User::class,
        middlewares: [
            'encrypt_cookies' => null,
            'validate_csrf_token' => 'App\Http\Middleware\CustomCsrf',
            'authenticate_session' => AuthenticateSession::class,
        ],
    );

    expect($config->middlewares['encrypt_cookies'])->toBeNull()
        ->and($config->middlewares['validate_csrf_token'])->toBe('App\Http\Middleware\CustomCsrf')
        ->and($config->middlewares['authenticate_session'])->toBe(AuthenticateSession::class);
});
