<?php

declare(strict_types=1);

namespace Deplox\Shield\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Deplox\Shield\Concerns\HasTokens;
use Deplox\Shield\Contracts\HasTokens as HasTokensContract;

final class AlternateUser extends Authenticatable implements HasTokensContract
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory;

    use HasTokens;
    use HasUlids;
}
