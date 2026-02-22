<?php

declare(strict_types=1);

namespace Deplox\Support\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;

/**
 * Inspired by https://github.com/tighten/parental
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasParent
{
    /**
     * @var bool
     */
    public $hasParent = true;

    /**
     * @throws ReflectionException
     */
    public static function bootHasParent(): void
    {
        static::creating(function ($model): void {
            if ($model->parentHasHasChildrenTrait()) {
                $model->forceFill(
                    [$model->getInheritanceColumn() => $model->classToAlias($model::class)]
                );
            }
        });

        static::addGlobalScope(function ($query): void {
            $instance = new static;

            if ($instance->parentHasHasChildrenTrait()) {
                $query->where($query->getModel()->getTable().'.'.$instance->getInheritanceColumn(), $instance->classToAlias($instance::class));
            }
        });
    }

    public function parentHasHasChildrenTrait(): bool
    {
        return $this->hasChildren ?? false;
    }

    /**
     * @throws ReflectionException
     */
    public function getTable(): string
    {
        if (! isset($this->table)) {
            return str_replace('\\', '', Str::snake(Str::plural(class_basename($this->getParentClass()))));
        }

        return $this->table;
    }

    /**
     * @throws ReflectionException
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this->getParentClass())).'_'.$this->primaryKey;
    }

    /**
     * @param  string  $related
     * @param  null|Model  $instance
     *
     * @throws ReflectionException
     */
    public function joiningTable($related, $instance = null): string
    {
        $relatedClassName = method_exists((new $related), 'getClassNameForRelationships')
            ? (new $related)->getClassNameForRelationships()
            : class_basename($related);

        $models = [
            Str::snake($relatedClassName),
            Str::snake($this->getClassNameForRelationships()),
        ];

        sort($models);

        return mb_strtolower(implode('_', $models));
    }

    /**
     * @throws ReflectionException
     */
    public function getClassNameForRelationships(): string
    {
        return class_basename($this->getParentClass());
    }

    /**
     * Get the class name for polymorphic relations.
     *
     * @throws ReflectionException
     */
    public function getMorphClass(): string
    {
        $parentClass = $this->getParentClass();

        return (new $parentClass)->getMorphClass();
    }

    /**
     * Get the class name for poly-type collections
     *
     * @throws ReflectionException
     */
    public function getClassNameForSerialization(): string
    {
        return $this->getParentClass();
    }

    /**
     * Get the class name for Parent Class.
     *
     * @throws ReflectionException
     */
    protected function getParentClass(): string
    {
        static $parentClassName;

        return $parentClassName ?: $parentClassName = new ReflectionClass($this)->getParentClass()->getName();
    }
}
