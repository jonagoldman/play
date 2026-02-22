<?php

declare(strict_types=1);

namespace JonaGoldman\Auth\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

interface IsAuthToken
{
    public static function findByToken(string $token): ?static;

    public function owner(): BelongsTo;

    public function setPlain(string $value): void;

    public function touchLastUsedAt(): void;
}
