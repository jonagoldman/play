<?php

declare(strict_types=1);

namespace App\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'plain' => $this->plain,
            'token' => $this->plain ? $this->getKey().'|'.$this->plain : null,
            // 'token' => $this->when($this->plain, fn () => $this->getKey().'|'.$this->plain),
            'expires_at' => $this->expires_at->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
