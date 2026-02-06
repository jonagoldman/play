<?php

declare(strict_types=1);

namespace App\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Facades\Context;
use Override;

final class UserApiResource extends JsonApiResource
{
    /**
     * The resource's relationships.
     */
    public $relationships = [
        'tokens' => TokenApiResource::class,
    ];

    #[Override]
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'verified_at' => $this->verified_at?->format('d/m/Y H:i:s'),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }

    // #[Override]
    // public function with($request): array
    // {
    //     return [
    //         'meta' => [
    //             'request_id' => Context::get('requestId'),
    //         ],
    //     ];
    // }
}
