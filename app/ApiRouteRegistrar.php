<?php

declare(strict_types=1);

namespace App;

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\UserTokenController;
use Illuminate\Routing\Router;

final readonly class ApiRouteRegistrar
{
    /**
     * @param  \Illuminate\Routing\RouteRegistrar|Router  $router  @phpstan-ignore parameter.phpDocType
     */
    public function __invoke(Router $router): void
    {
        $router->controller(ApiController::class)
            ->group(function (Router $router): void {
                $router->get('/', 'index');
            });

        $router->controller(AuthController::class)
            ->group(function (Router $router): void {
                $router->post('register', 'register');
                $router->post('login', 'login');
            });

        $router->middleware(['auth:dynamic'])
            ->scopeBindings()
            ->group(function (Router $router): void {
                $router->controller(AuthController::class)
                    ->group(function (Router $router): void {
                        $router->post('logout', 'logout');
                        $router->get('user', 'user');
                    });

                $router->apiResource('users', UserController::class);
                $router->apiResource('users.tokens', UserTokenController::class)
                    ->except(['update'])
                    ->scoped();
            });
    }
}
