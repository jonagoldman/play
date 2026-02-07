<?php

declare(strict_types=1);

namespace App\Models;

use App\Resources\TokenResource;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JonaGoldman\Auth\Concerns\IsAuthToken;

#[UseResource(TokenResource::class)]
final class Token extends Model
{
    use HasFactory;
    use IsAuthToken;
}
