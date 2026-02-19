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

use function hash;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property-read string|null $plain
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
     * Find a token by its raw value (decorated or plain).
     */
    public static function findByToken(string $token): ?static
    {
        if ($token === '') {
            return null;
        }

        $random = app(AuthConfig::class)->extractRandom($token);

        if ($random === null) {
            return null;
        }

        /** @var static|null */
        return static::query()->where('token', hash('sha256', $random))->first();
    }

    public function setPlain(string $value): void
    {
        $this->plain = $value;
    }

    public function initializeIsAuthToken(): void
    {
        $this->fillable = array_merge($this->fillable, [
            'name',
            'type',
            'token',
            'expires_at',
        ]);

        $this->hidden = array_merge($this->hidden, [
            'token',
        ]);

        $this->appends = array_merge($this->appends, [
            'expired',
        ]);
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

        $connection = $this->getConnection();

        if (method_exists($connection, 'hasModifiedRecords') &&
            method_exists($connection, 'setRecordModificationState')) {
            $hasModifiedRecords = $connection->hasModifiedRecords();
            $this->forceFill(['last_used_at' => now()])->saveQuietly();
            $connection->setRecordModificationState($hasModifiedRecords);
        } else {
            $this->forceFill(['last_used_at' => now()])->saveQuietly();
        }
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
            'expires_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
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
