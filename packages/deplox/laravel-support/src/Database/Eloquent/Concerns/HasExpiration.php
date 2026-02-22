<?php

declare(strict_types=1);

namespace Deplox\Support\Database\Eloquent\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasExpiration
{
    public function expires(DateTimeInterface $expiresAt): static
    {
        $this->expires_at = $expiresAt;

        return $this;
    }

    public function addMonths(int $months): static
    {
        return $this->expires($this->fromExpiration()->addMonths($months));
    }

    public function addWeeks(int $weeks): static
    {
        return $this->expires($this->fromExpiration()->addWeeks($weeks));
    }

    public function addDays(int $days): static
    {
        return $this->expires($this->fromExpiration()->addDays($days));
    }

    public function addHours(int $hours): static
    {
        return $this->expires($this->fromExpiration()->addHours($hours));
    }

    public function addMinutes(int $minutes): static
    {
        return $this->expires($this->fromExpiration()->addMinutes($minutes));
    }

    #[Scope]
    public function whereExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    #[Scope]
    public function whereNotExpired(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    protected function expired(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->expires_at !== null && $this->expires_at->isPast(),
            set: fn (bool $expired): array => [
                'expires_at' => $expired ? now()->toDateTimeString() : null,
            ],
        );
    }

    private function fromExpiration(): CarbonImmutable
    {
        return ($this->expires_at ?? now())->toImmutable();
    }
}
