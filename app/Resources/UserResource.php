<?php

declare(strict_types=1);

namespace App\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Context;
use JonaGoldman\Auth\Resources\TokenResource;

/**
 * @mixin \App\Models\User
 */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'verified_at' => $this->verified_at?->format('d/m/Y H:i:s'),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),

            'tokens' => TokenResource::collection($this->whenLoaded('tokens')),
            'tokens_count' => $this->whenCounted('tokens'),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'request_id' => Context::get('requestId'),
            ],
        ];
    }
}
