<?php

declare(strict_types=1);

namespace JonaGoldman\Support\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSlugs
{
    /**
     * The model's sluggable attributes map.
     */
    protected $sluggable = [
        'name' => 'slug',
    ];

    /**
     * Boot the trait for the model class.
     */
    public static function bootHasSlugs(): void
    {
        // The saving event is dispatched before creating or updating the model, even if the attributes have not changed.
        static::saving(function (Model $model): void {
            $model->setSluggableValues();
        });
    }

    /**
     * Generate a URL friendly slug from a given string.
     */
    public static function slugify(string $value): string
    {
        return Str::slug($value);
    }

    /**
     * Set the sluggable attributes for the model.
     */
    public function setSluggableValues(): self
    {
        foreach ($this->getSluggable() as $source => $target) {
            $this->{$target} = $this->slugify($this->{$source});
        }

        return $this;
    }

    /**
     * Get the sluggable attributes map for the model.
     */
    public function getSluggable(): array
    {
        return $this->sluggable;
    }
}
