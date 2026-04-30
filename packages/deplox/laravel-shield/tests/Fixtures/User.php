<?php

declare(strict_types=1);

namespace Deplox\Shield\Tests\Fixtures;

use Deplox\Shield\Concerns\HasTokens;
use Deplox\Shield\Contracts\HasTokens as HasTokensContract;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
final class User extends Authenticatable implements CanResetPasswordContract, HasTokensContract, MustVerifyEmailContract
{
    use CanResetPassword;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasTokens;
    use HasUlids;
    use MustVerifyEmail;

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
