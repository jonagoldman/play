<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use JonaGoldman\Essentials\Middlewares\UseHeaderGuards;
use JonaGoldman\Essentials\Middlewares\UseRequestId;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', [
            UseRequestId::class,
            UseHeaderGuards::class,
        ]);

        $middleware->prependToGroup('api', [
            UseRequestId::class,
            UseHeaderGuards::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
