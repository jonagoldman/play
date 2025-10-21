<?php

declare(strict_types=1);

namespace JonaGoldman\Support\Database\Eloquent\Concerns;

use App\Models\Token;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasExpiration
{
    public function expires(DateTimeInterface $expiresAt): Token
    {
        $this->expires_at = $expiresAt;

        return $this;
    }

    public function addMonths(int $months): Token
    {
        $expires = $this->expires_at ?: now();

        return $this->expires($expires->addMonths($months));
    }

    public function addWeeks(int $weeks): Token
    {
        $expires = $this->expires_at ?: now();

        return $this->expires($expires->addWeeks($weeks));
    }

    public function addDays(int $days): Token
    {
        $expires = $this->expires_at ?: now();

        return $this->expires($expires->addDays($days));
    }

    public function addHours(int $hours): Token
    {
        $expires = $this->expires_at ?: now();

        return $this->expires($expires->addHours($hours));
    }

    public function addMinutes(int $minutes): Token
    {
        $expires = $this->expires_at ?: now();

        return $this->expires($expires->addMinutes($minutes));
    }

    protected function expired(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->expires_at && $this->expires_at->isPast(),
            set: fn (bool $expired): array => [
                'expires_at' => $expired ? now()->toDateTimeString() : null,
            ],
        );
    }
}
