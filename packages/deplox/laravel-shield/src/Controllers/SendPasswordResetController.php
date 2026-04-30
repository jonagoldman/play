<?php

declare(strict_types=1);

namespace Deplox\Shield\Controllers;

use Deplox\Shield\Actions\SendPasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class SendPasswordResetController
{
    public function __invoke(Request $request, SendPasswordReset $action): JsonResponse
    {
        $status = $action($request->all());

        return new JsonResponse(
            ['status' => __($status)],
            $status === PasswordBroker::RESET_LINK_SENT ? 200 : 422,
        );
    }
}
