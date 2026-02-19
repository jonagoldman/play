<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use JonaGoldman\Auth\Concerns\HasTokens;
use JonaGoldman\Auth\Contracts\HasTokens as HasTokensContract;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $email
 * @property-read string $password
 * @property-read string|null $remember_token
 * @property-read \Carbon\CarbonInterface|null $verified_at
 * @property-read \Carbon\CarbonInterface $created_at
 * @property-read \Carbon\CarbonInterface $updated_at
 */
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable implements HasTokensContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasTokens;
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            $this->getAuthPasswordName() => 'hashed',
            $this->getVerifiedAtName() => 'datetime',
        ];
    }
}
