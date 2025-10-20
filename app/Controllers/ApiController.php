<?php

declare(strict_types=1);

namespace App\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JonaGoldman\Essentials\Facades\Overseer;

final class ApiController
{
    public function index(Request $request): JsonResponse
    {
        $result = [
            'message' => 'HTTP request received.',
            'data' => Overseer::inspect(),
        ];

        if (defined('LARAVEL_START')) {
            $ms = round((microtime(true) - LARAVEL_START) * 1000);
            $result['message'] .= " Response rendered in {$ms}ms.";
        }

        return new JsonResponse($result);
    }
}
