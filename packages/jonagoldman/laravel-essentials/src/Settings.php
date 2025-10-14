<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials;

use Illuminate\Contracts\Support\Arrayable;

final readonly class Settings implements Arrayable
{
    public function __construct(
        public bool $fakeSleep,
        public bool $preventStrayRequests,
        public bool $forceHttps,
        public bool $aggressivePrefetching,
        public bool $immutableDates,
        public bool $modelUnguard,
        public bool $modelStrict,
        public bool $modelAutomaticEagerLoadRelationships,
        public bool $prohibitDestructiveCommands,
        public bool $setDefaultPasswords,
        public int $defaultStringLength,
        public string $defaultMorphKeyType,
    ) {}

    public static function fromArray(?array $data): self
    {
        return new self(
            $data['fake_sleep'] ?? true,
            $data['prevent_stray_requests'] ?? true,
            $data['force_https'] ?? true,
            $data['aggressive_prefetching'] ?? true,
            $data['immutable_dates'] ?? true,
            $data['model_unguard'] ?? false,
            $data['model_strict'] ?? true,
            $data['model_automatic_eager_load_relationships'] ?? true,
            $data['prohibit_destructive_commands'] ?? true,
            $data['set_default_passwords'] ?? true,
            $data['default_string_length'] ?? 255,
            $data['default_morph_key_type'] ?? 'int',
        );
    }

    public function toArray(): array
    {
        return [
            'fake_sleep' => $this->fakeSleep,
            'prevent_stray_requests' => $this->preventStrayRequests,
            'force_https' => $this->forceHttps,
            'aggressive_prefetching' => $this->aggressivePrefetching,
            'immutable_dates' => $this->immutableDates,
            'model_unguard' => $this->modelUnguard,
            'model_strict' => $this->modelStrict,
            'model_automatic_eager_load_relationships' => $this->modelAutomaticEagerLoadRelationships,
            'prohibit_destructive_commands' => $this->prohibitDestructiveCommands,
            'set_default_passwords' => $this->setDefaultPasswords,
            'default_string_length' => $this->defaultStringLength,
            'default_morph_key_type' => $this->defaultMorphKeyType,
        ];
    }
}
