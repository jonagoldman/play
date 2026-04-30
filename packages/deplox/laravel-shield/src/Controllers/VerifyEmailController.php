<?php

declare(strict_types=1);

namespace Deplox\Shield\Controllers;

use Deplox\Shield\Actions\VerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class VerifyEmailController
{
    public function __invoke(Request $request, VerifyEmail $action, string $id, string $hash): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            throw new HttpException(403, 'Invalid signature.');
        }

        $verified = $action($id, $hash);

        return new JsonResponse([
            'status' => $verified ? 'verified' : 'verification-failed',
        ], $verified ? 200 : 422);
    }
}
