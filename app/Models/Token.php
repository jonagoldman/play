<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JonaGoldman\Auth\Concerns\IsAuthToken;
use JonaGoldman\Auth\Contracts\IsAuthToken as IsAuthTokenContract;
use JonaGoldman\Auth\Database\Factories\TokenFactory;
use JonaGoldman\Auth\Policies\TokenPolicy;
use JonaGoldman\Auth\Resources\TokenResource;

#[UseFactory(TokenFactory::class)]
#[UsePolicy(TokenPolicy::class)]
#[UseResource(TokenResource::class)]
final class Token extends Model implements IsAuthTokenContract
{
    /** @use HasFactory<TokenFactory> */
    use HasFactory;

    use IsAuthToken;
}
