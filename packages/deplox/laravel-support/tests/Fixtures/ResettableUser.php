<?php

declare(strict_types=1);

namespace Deplox\Support\Tests\Fixtures;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property-read string $id
 * @property-read string $email
 * @property-read string $password
 */
final class ResettableUser extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword;
    use HasUlids;

    public $timestamps = false;

    protected $table = 'resettable_users';

    protected $guarded = [];

    protected $hidden = ['password'];
}
