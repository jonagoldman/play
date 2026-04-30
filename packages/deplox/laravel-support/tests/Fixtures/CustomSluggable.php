<?php

declare(strict_types=1);

namespace Deplox\Support\Tests\Fixtures;

use Deplox\Support\Database\Eloquent\Concerns\HasSlugs;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $title
 * @property string $subtitle
 * @property-read string $permalink
 * @property-read string $sub_slug
 */
final class CustomSluggable extends Model
{
    use HasSlugs;

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $sluggable = [
        'title' => 'permalink',
        'subtitle' => 'sub_slug',
    ];
}
