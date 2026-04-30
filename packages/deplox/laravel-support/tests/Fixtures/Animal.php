<?php

declare(strict_types=1);

namespace Deplox\Support\Tests\Fixtures;

use Deplox\Support\Database\Eloquent\Concerns\HasChildren;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $type
 */
class Animal extends Model
{
    use HasChildren;
    use HasUlids;

    public $timestamps = false;

    protected $table = 'animals';

    protected $guarded = [];

    /** @var array<string, class-string<Animal>> */
    protected $childTypes = [
        'dog' => Dog::class,
        'cat' => Cat::class,
    ];
}
