<?php

declare(strict_types=1);

namespace Deplox\Shield\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CsrfCookieController
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
