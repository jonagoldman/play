<?php

declare(strict_types=1);

namespace Deplox\Support\Database\Eloquent\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Inspired by https://github.com/tighten/parental
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasChildren
{
    /**
     * Tracks parents currently inside their boot lifecycle, keyed by class name.
     *
     * @var array<class-string, bool>
     */
    protected static array $parentBootingClasses = [];

    /**
     * @var bool
     */
    protected $hasChildren = true;

    /**
     * Bootstrap the HasChildren trait.
     *
     * Marks the parent class as booting, then registers a 'booted' callback that
     * clears the flag once the boot lifecycle completes. The callback is registered
     * via parent::registerModelEvent to bypass this trait's override (which would
     * otherwise propagate to all child classes and recurse).
     */
    public static function bootHasChildren(): void
    {
        self::$parentBootingClasses[static::class] = true;

        parent::registerModelEvent('booted', static function (): void {
            unset(self::$parentBootingClasses[static::class]);
        });
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false): self
    {
        $model = isset($attributes[$this->getInheritanceColumn()])
            ? $this->getChildModel($attributes)
            : new static(((array) $attributes));

        $model->exists = $exists;

        $model->setConnection(
            $this->getConnectionName()
        );

        return $model;
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param  array  $attributes
     * @param  string|null  $connection
     * @return static
     */
    public function newFromBuilder($attributes = [], $connection = null): self
    {
        $attributes = (array) $attributes;

        $inheritanceAttributes = [];
        $inheritanceColumn = $this->getInheritanceColumn();

        if (isset($attributes[$inheritanceColumn])) {
            $inheritanceAttributes[$inheritanceColumn] = $attributes[$inheritanceColumn];
        }

        $model = $this->newInstance($inheritanceAttributes, true);

        $model->setRawAttributes($attributes, true);

        $model->setConnection($connection ?: $this->getConnectionName());

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string|null  $relation
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null): BelongsTo
    {
        $instance = $this->newRelatedInstance($related);

        if (is_null($foreignKey) && method_exists($instance, 'hasParent') && $instance->hasParent()) {
            $foreignKey = Str::snake($instance->getClassNameForRelationships()).'_'.$instance->getKeyName();
        }

        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        return parent::belongsTo($related, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     */
    public function hasMany($related, $foreignKey = null, $localKey = null): HasMany
    {
        return parent::hasMany($related, $foreignKey, $localKey);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param  string  $related
     * @param  string|null  $table
     * @param  string|null  $foreignPivotKey
     * @param  string|null  $relatedPivotKey
     * @param  string|null  $parentKey
     * @param  string|null  $relatedKey
     * @param  string|null  $relation
     */
    public function belongsToMany(
        $related,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $relation = null
    ): BelongsToMany {
        $instance = $this->newRelatedInstance($related);

        if (is_null($table) && method_exists($instance, 'hasParent') && $instance->hasParent()) {
            $table = $this->joiningTable($instance->getClassNameForRelationships());
        }

        return parent::belongsToMany(
            $related,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relation,
        );
    }

    public function getClassNameForRelationships(): string
    {
        return class_basename($this);
    }

    public function getInheritanceColumn(): string
    {
        return property_exists($this, 'childColumn') ? $this->childColumn : 'type';
    }

    /**
     * @param  mixed  $aliasOrClass
     */
    public function classFromAlias($aliasOrClass): string
    {
        $childTypes = $this->getChildTypes();

        // Handling Enum casting for `type` column
        if ($aliasOrClass instanceof UnitEnum) {
            $aliasOrClass = $aliasOrClass->value;
        }

        return $childTypes[$aliasOrClass] ?? $aliasOrClass;
    }

    public function classToAlias(string $className): string
    {
        $childTypes = $this->getChildTypes();

        if (in_array($className, $childTypes)) {
            return array_search($className, $childTypes);
        }

        return $className;
    }

    public function getChildTypes(): array
    {
        return property_exists($this, 'childTypes') ? $this->childTypes : [];
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  Closure|string  $callback
     */
    protected static function registerModelEvent($event, $callback): void
    {
        parent::registerModelEvent($event, $callback);

        // Short-circuit before instantiating: child propagation only runs when
        // we're being called on the parent class itself AND the parent isn't
        // currently booting. Skipping early avoids `new static` during a child
        // class's boot, which Laravel 13 forbids (Model::bootIfNotBooted).
        if (static::class !== self::class || self::parentIsBooting()) {
            return;
        }

        foreach ((new static)->getChildTypes() as $childClass) {
            if ($childClass !== self::class) {
                $childClass::registerModelEvent($event, $callback);
            }
        }
    }

    protected static function parentIsBooting(): bool
    {
        return self::$parentBootingClasses[static::class] ?? false;
    }

    /**
     * @return mixed
     */
    protected function getChildModel(array $attributes)
    {
        $className = $this->classFromAlias(
            $attributes[$this->getInheritanceColumn()]
        );

        return new $className($attributes);
    }
}
