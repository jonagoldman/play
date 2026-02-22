<?php

declare(strict_types=1);

namespace App\Models;

use Deplox\Shield\Concerns\IsAuthToken;
use Deplox\Shield\Contracts\IsAuthToken as IsAuthTokenContract;
use Deplox\Shield\Database\Factories\TokenFactory;
use Deplox\Shield\Policies\TokenPolicy;
use Deplox\Shield\Resources\TokenResource;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(TokenFactory::class)]
#[UsePolicy(TokenPolicy::class)]
#[UseResource(TokenResource::class)]
final class Token extends Model implements IsAuthTokenContract
{
    /** @use HasFactory<TokenFactory> */
    use HasFactory;

    use IsAuthToken;
}
