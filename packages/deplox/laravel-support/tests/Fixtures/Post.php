<?php

declare(strict_types=1);

namespace Deplox\Support\Tests\Fixtures;

use Deplox\Support\Database\Eloquent\Concerns\CanIncludeRelationships;
use Deplox\Support\Database\Eloquent\Concerns\HasExpiration;
use Deplox\Support\Database\Eloquent\Concerns\HasSlugs;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read ?string $author_id
 * @property-read ?\Carbon\CarbonInterface $expires_at
 */
final class Post extends Model
{
    use CanIncludeRelationships;
    use HasExpiration;
    use HasSlugs;
    use HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
