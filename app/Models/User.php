<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $email
 * @property-read string $password
 * @property-read string|null $remember_token
 * @property-read CarbonInterface|null $verified_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUlids;
    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function token(): ?HasOne
    {
        return $this->tokens()->one()->latestOfMany();
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class);
    }

    public function tokenCan(string $ability): bool
    {
        return $this->relationLoaded('token') && $this->token->can($ability);
    }

    protected function casts(): array
    {
        return [
            $this->getAuthPasswordName() => 'hashed',
            $this->getVerifiedAtName() => 'datetime',
        ];
    }

    /**
     * @param  CarbonInterface  $date
     */
    // protected function serializeDate(\DateTimeInterface $date)
    // {
    //     return $date->toIso8601ZuluString();
    // }
}
