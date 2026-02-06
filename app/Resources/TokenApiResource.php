<?php

declare(strict_types=1);

namespace App\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Override;

/**
 * @mixin \App\Models\Token
 */
final class TokenApiResource extends JsonApiResource
{
    #[Override]
    public function toAttributes(Request $request): array
    {
        $attributes = [
            'user_id' => $this->user_id,
            'type' => $this->type,
            'expired' => $this->expired,
            'expires_at' => $this->expires_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];

        if ($this->plain) {
            $attributes['plain'] = $this->plain;
            $attributes['token'] = $this->getKey().'|'.$this->plain;
        }

        return $attributes;
    }
}
