<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use JonaGoldman\Auth\Middlewares\StatefulFrontend;
use JonaGoldman\Essentials\Middlewares\UseHeaderGuards;
use JonaGoldman\Essentials\Middlewares\UseRequestId;

// use Illuminate\Support\Facades\Config;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // $middleware->trustHosts(fn () => explode(',', Config::get('app.stateful_domains')));

        // $middleware->statefulApi();

        $middleware->appendToGroup('web', [
            UseRequestId::class,
            UseHeaderGuards::class,
        ]);

        $middleware->appendToGroup('api', [
            StatefulFrontend::class,
            UseRequestId::class,
            UseHeaderGuards::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') ?: $request->expectsJson();
        });
    })->create();
