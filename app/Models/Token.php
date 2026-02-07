<?php

declare(strict_types=1);

namespace App\Models;

use App\Resources\TokenResource;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use JonaGoldman\Auth\Models\Token as BaseToken;

#[UseResource(TokenResource::class)]
final class Token extends BaseToken {}
