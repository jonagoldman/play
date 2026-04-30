<?php

declare(strict_types=1);

namespace Deplox\Shield\Controllers;

use Deplox\Shield\Actions\SendEmailVerification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class SendEmailVerificationController
{
    public function __invoke(Request $request, SendEmailVerification $action): JsonResponse
    {
        /** @var Authenticatable&MustVerifyEmail|null $user */
        $user = $request->user();

        if (! $user instanceof MustVerifyEmail) {
            return new JsonResponse(['status' => 'unauthenticated'], 401);
        }

        $sent = $action($user);

        return new JsonResponse([
            'status' => $sent ? 'verification-link-sent' : 'already-verified',
        ]);
    }
}
