<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JonaGoldman\Auth\AuthConfig;
use JonaGoldman\Auth\Enums\TokenType;
use JonaGoldman\Support\Database\Eloquent\Concerns\HasExpiration;

use function explode;
use function hash;
use function hash_equals;
use function mb_strpos;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait IsAuthToken
{
    use HasExpiration;
    use HasUlids;
    use MassPrunable;

    protected ?string $plain = null;

    public static function bootIsAuthToken(): void
    {
        static::created(function (self $token): void {
            $token->append('plain');
        });
    }

    /**
     * Find a token by its raw value (supports `id|secret` format).
     */
    public static function findByToken(string $token): ?static
    {
        $query = static::query();

        if (mb_strpos($token, '|') === false) {
            /** @var static|null */
            return $query->where('token', hash('sha256', $token))->first();
        }

        [$id, $secret] = explode('|', $token, 2);

        /** @var static|null $instance */
        $instance = $query->find($id);

        if ($instance && hash_equals($instance->token, hash('sha256', $secret))) {
            return $instance;
        }

        return null;
    }

    public function initializeIsAuthToken(): void
    {
        $this->fillable = [
            'type',
            'token',
            'expires_at',
        ];

        $this->hidden = [
            'token',
        ];

        $this->appends = [
            'expired',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(app(AuthConfig::class)->userModel);
    }

    /**
     * Update last_used_at with debounce to avoid excessive writes.
     */
    public function touchLastUsedAt(): void
    {
        $debounce = app(AuthConfig::class)->lastUsedAtDebounce;

        if ($this->last_used_at && $this->last_used_at->diffInSeconds(now()) < $debounce) {
            return;
        }

        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        $pruneDays = app(AuthConfig::class)->pruneDays;

        return static::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->subDays($pruneDays));
    }

    protected function casts(): array
    {
        return [
            'type' => TokenType::class,
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    protected function plain(): Attribute
    {
        return Attribute::make(get: fn () => $this->plain);
    }

    protected function token(): Attribute
    {
        return Attribute::make(set: fn ($value) => hash('sha256', $this->plain = $value));
    }
}
