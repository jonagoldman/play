<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string|null $name
 * @property-read \JonaGoldman\Auth\Enums\TokenType $type
 * @property-read string|null $plain
 * @property-read bool $expired
 * @property-read \Carbon\CarbonImmutable|null $expires_at
 * @property-read \Carbon\CarbonImmutable|null $last_used_at
 * @property-read \Carbon\CarbonImmutable|null $created_at
 * @property-read \Carbon\CarbonImmutable|null $updated_at
 */
final class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'type' => $this->type,
            'token' => $this->when((bool) $this->plain, $this->plain),
            'expired' => $this->expired,
            'expires_at' => $this->expires_at?->toIso8601ZuluString(),
            'last_used_at' => $this->last_used_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
