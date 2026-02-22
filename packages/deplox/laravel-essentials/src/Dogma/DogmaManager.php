<?php

declare(strict_types=1);

namespace Deplox\Essentials\Dogma;

use Deplox\Essentials\Dogma\Principles\DatabasePrinciple;
use Deplox\Essentials\Dogma\Principles\GeneralPrinciple;
use Deplox\Essentials\Dogma\Principles\HttpPrinciple;
use Deplox\Essentials\Dogma\Principles\ModelPrinciple;
use Deplox\Essentials\EssentialsConfig;

final readonly class DogmaManager
{
    public function __construct(
        private EssentialsConfig $config,
    ) {}

    public function apply(): void
    {
        HttpPrinciple::apply($this->config);
        ModelPrinciple::apply($this->config);
        DatabasePrinciple::apply($this->config);
        GeneralPrinciple::apply($this->config);
    }

    public function status(): array
    {
        return [
            'http' => HttpPrinciple::status(),
            'model' => ModelPrinciple::status(),
            'database' => DatabasePrinciple::status(),
            'general' => GeneralPrinciple::status(),
        ];
    }
}
