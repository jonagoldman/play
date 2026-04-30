<?php

declare(strict_types=1);

namespace Deplox\Shield\Controllers;

use Deplox\Shield\Actions\ResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ResetPasswordController
{
    public function __invoke(Request $request, ResetPassword $action): JsonResponse
    {
        $status = $action($request->all());

        return new JsonResponse(
            ['status' => __($status)],
            $status === PasswordBroker::PASSWORD_RESET ? 200 : 422,
        );
    }
}
