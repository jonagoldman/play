<?php

declare(strict_types=1);

namespace Deplox\Support\Tests\Fixtures;

use Deplox\Support\Database\Eloquent\Concerns\InMemory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $code
 * @property-read string $name
 */
final class Country extends Model
{
    use InMemory;

    public $timestamps = false;

    protected $guarded = [];

    /** @var list<array{id: int, code: string, name: string}> */
    protected $rows = [
        ['id' => 1, 'code' => 'US', 'name' => 'United States'],
        ['id' => 2, 'code' => 'GB', 'name' => 'United Kingdom'],
        ['id' => 3, 'code' => 'FR', 'name' => 'France'],
    ];
}
