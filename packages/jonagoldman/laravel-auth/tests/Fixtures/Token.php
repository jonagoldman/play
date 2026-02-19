<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JonaGoldman\Auth\Concerns\IsAuthToken;
use JonaGoldman\Auth\Database\Factories\TokenFactory;

#[UseFactory(TokenFactory::class)]
final class Token extends Model
{
    /** @use HasFactory<TokenFactory> */
    use HasFactory;

    use IsAuthToken;
}
