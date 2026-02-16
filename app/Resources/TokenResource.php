<?php

declare(strict_types=1);

namespace App\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Token
 */
final class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'type' => $this->type,
            $this->mergeWhen($this->plain, [
                'plain' => $this->plain,
                'token' => $this->getKey().'|'.$this->plain,
            ]),
            'expired' => $this->expired,
            'expires_at' => $this->expires_at?->toIso8601ZuluString(),
            'last_used_at' => $this->last_used_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
