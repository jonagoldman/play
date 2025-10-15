<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;

final class Inspector
{
    /**
     * @return array<string>
     */
    public function inspect(): array
    {
        return [];
    }
}
