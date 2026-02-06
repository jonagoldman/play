<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTokens;
use App\Resources\UserResource;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Casts\AsBinary;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
#[UseResource(UserResource::class)]
final class User extends Authenticatable
{
    use HasFactory;
    use HasTokens;
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

    protected function casts(): array
    {
        return [
            // 'id' => AsBinary::ulid(),
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
