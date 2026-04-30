<?php

declare(strict_types=1);

namespace Deplox\Support\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSlugs
{
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
     *
     * Override this method to customize the source-to-target mapping. The default
     * is `['name' => 'slug']`. Implemented as a method (rather than a property)
     * to avoid PHP 8.4 trait property conflicts with final classes that may
     * redefine the same name.
     *
     * @return array<string, string>
     */
    public function getSluggable(): array
    {
        return $this->sluggable ?? ['name' => 'slug'];
    }
}
