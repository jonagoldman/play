<?php

declare(strict_types=1);

namespace Deplox\Shield\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Deplox\Shield\Concerns\IsAuthToken;
use Deplox\Shield\Contracts\IsAuthToken as IsAuthTokenContract;
use Deplox\Shield\Database\Factories\TokenFactory;

#[UseFactory(TokenFactory::class)]
final class Token extends Model implements IsAuthTokenContract
{
    /** @use HasFactory<TokenFactory> */
    use HasFactory;

    use IsAuthToken;
}
