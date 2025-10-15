<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Dogma;

use JonaGoldman\Essentials\Dogma\Principles\DatabasePrinciple;
use JonaGoldman\Essentials\Dogma\Principles\GeneralPrinciple;
use JonaGoldman\Essentials\Dogma\Principles\HttpPrinciple;
use JonaGoldman\Essentials\Dogma\Principles\ModelPrinciple;
use JonaGoldman\Essentials\EssentialsConfig;

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
