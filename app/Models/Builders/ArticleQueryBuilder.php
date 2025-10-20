<?php

declare(strict_types=1);

namespace App\Models\Builders;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

final class ArticleQueryBuilder extends Builder
{
    public function runningBetween(DateTimeInterface $start, DateTimeInterface $end)
    {
        return $this->whereBetween('published_at', [$start, $end]);
    }
}
