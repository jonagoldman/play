<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TokenType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JonaGoldman\Support\Database\Eloquent\Concerns\HasExpiration;

final class Token extends Model
{
    use HasExpiration;
    use HasFactory;
    use HasUlids;

    protected ?string $plain = null;

    protected $fillable = [
        'type',
        'token',
        'expires_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected $appends = [
        'expired',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        self::created(function (Token $token) {
            $token->append('plain');
        });
    }

    protected function casts(): array
    {
        return [
            'type' => TokenType::class,
            'expires_at' => 'datetime',
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
