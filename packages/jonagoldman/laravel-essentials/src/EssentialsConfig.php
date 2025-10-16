<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials;

use Illuminate\Contracts\Support\Arrayable;

final readonly class EssentialsConfig implements Arrayable
{
    public function __construct(
        public bool $fakeSleep,
        public bool $preventStrayRequests,
        public bool $forceHttps,
        public bool $aggressivePrefetching,
        public bool $immutableDates,
        public bool $unguardModel,
        public bool $strictModel,
        public bool $automaticEagerLoadRelationships,
        public bool $requireMorphMap,
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
            $data['unguard_model'] ?? false,
            $data['strict_model'] ?? true,
            $data['automatic_eager_load_relationships'] ?? true,
            $data['require_morph_map'] ?? true,
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
            'unguard_model' => $this->unguardModel,
            'strict_model' => $this->strictModel,
            'automatic_eager_load_relationships' => $this->automaticEagerLoadRelationships,
            'require_morph_map' => $this->requireMorphMap,
            'prohibit_destructive_commands' => $this->prohibitDestructiveCommands,
            'set_default_passwords' => $this->setDefaultPasswords,
            'default_string_length' => $this->defaultStringLength,
            'default_morph_key_type' => $this->defaultMorphKeyType,
        ];
    }
}
