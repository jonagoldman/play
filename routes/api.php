<?php

declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\UserController;
use App\Controllers\UserTokenController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ApiController::class, 'index']);

Route::apiResource('users', UserController::class);

Route::apiResource('users.tokens', UserTokenController::class)
    ->except(['update'])
    ->scoped();
