<?php

declare(strict_types=1);

namespace Deplox\Support\Tests\Fixtures;

use Deplox\Support\Database\Eloquent\Concerns\HasParent;

final class Cat extends Animal
{
    use HasParent;
}
