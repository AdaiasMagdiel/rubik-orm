<?php

namespace AdaiasMagdiel\Rubik;

use AdaiasMagdiel\Rubik\Relation\BelongsToMany;
use AdaiasMagdiel\Rubik\Trait\Model as Traits;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Base class for all Rubik ORM models.
 * 
 * Represents a database table and provides Active Record functionality,
 * including CRUD operations, relationship management, and query scoping.
 *
 * @package AdaiasMagdiel\Rubik
 */
abstract class Model implements JsonSerializable
{
    use Traits\CrudTrait;
    use Traits\QueryTrait;
    use Traits\SchemaTrait;
    use Traits\SerializationTrait;

    /** 
     * The database table name for the model.
     * 
     * @var string 
     */
    protected static string $table = '';

    /** 
     * Associative array of column names and their values for the model instance.
     * 
     * @var array 
     */
    protected array $_data = [];

    /** 
     * Associative array of columns that have been modified since the last save.
     * 
     * @var array 
     */
    protected array $_dirty = [];

    /** 
     * Cache for loaded relationship results.
     * 
     * @var array 
     */
    protected array $_relationships = [];

    /** 
     * Indicates if the model exists in the database.
     * 
     * @var bool 
     */
    public bool $exists = false;

    /**
     * Define a "has one" relationship.
     *
     * This relationship indicates that the current model is the "parent"
     * and the related model contains a foreign key that points back to it.
     *
     * Example:
     *     User has one Phone
     *     - Foreign key on phones table:  user_id
     *     - Local key on users table:     id
     *
     * If no keys are provided, conventional defaults are used:
     * - $foreignKey defaults to $this->getForeignKey() (e.g. "user_id")
     * - $localKey defaults to static::primaryKey() (e.g. "id")
     *
     * @param class-string<\AdaiasMagdiel\Rubik\Model> $related
     *        Fully-qualified related model class name.
     * @param string|null $foreignKey
     *        Foreign key column on the related table.
     * @param string|null $localKey
     *        Local key column on the parent table.
     *
     * @return \AdaiasMagdiel\Rubik\Relation\HasOne
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): \AdaiasMagdiel\Rubik\Relation\HasOne
    {
        /** @var \AdaiasMagdiel\Rubik\Model $instance */
        $instance = new $related();

        // Convention: if the parent is User, the FK on the related table is "user_id"
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey   = $localKey   ?: static::primaryKey();

        return new \AdaiasMagdiel\Rubik\Relation\HasOne(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $localKey
        );
    }

    /**
     * Define a "has many" relationship.
     *
     * This relationship indicates that the current model is the "parent"
     * and the related model contains a foreign key pointing back to it.
     *
     * Example:
     *     User has many Post
     *     - Foreign key on posts table:  user_id
     *     - Local key on users table:    id
     *
     * If no keys are provided, conventional defaults are used:
     * - $foreignKey defaults to $this->getForeignKey() (e.g. "user_id")
     * - $localKey defaults to static::primaryKey() (e.g. "id")
     *
     * @param class-string<Model> $related   Fully-qualified related model class name.
     * @param string|null         $foreignKey Foreign key column on the related table.
     * @param string|null         $localKey   Local key column on the parent table.
     *
     * @return Relation\HasMany
     */
    public function hasMany(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): Relation\HasMany {
        /** @var Model $instance */
        $instance = new $related();

        // Convention: if the parent is User, the FK on the related table is "user_id"
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey   = $localKey   ?: static::primaryKey();

        return new Relation\HasMany(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $localKey
        );
    }

    /**
     * Define a "belongs to" relationship.
     *
     * This relationship indicates that the current model is the "child"
     * and it contains a foreign key that references the related (owner) model.
     *
     * Example:
     *     Post belongs to User
     *     - Foreign key on posts table:  user_id
     *     - Owner key on users table:    id
     *
     * If no keys are provided, conventional defaults are used:
     * - $foreignKey defaults to $instance->getForeignKey() (e.g. "user_id")
     *   NOTE: this assumes your foreign key is based on the related model name.
     * - $ownerKey defaults to $instance->primaryKey() (e.g. "id")
     *
     * @param class-string<Model> $related    Fully-qualified related model class name.
     * @param string|null         $foreignKey Foreign key column on the current (child) table.
     * @param string|null         $ownerKey   Owner key column on the related (parent) table.
     *
     * @return Relation\BelongsTo
     */
    public function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null
    ): Relation\BelongsTo {
        /** @var Model $instance */
        $instance = new $related();

        // Convention: if the related model is User, the FK on this model is "user_id"
        $foreignKey = $foreignKey ?: $instance->getForeignKey();
        $ownerKey   = $ownerKey   ?: $instance->primaryKey();

        return new Relation\BelongsTo(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $ownerKey
        );
    }

    /**
     * Define a "belongs to many" (many-to-many) relationship.
     *
     * This relationship indicates that the current model is related to many
     * instances of another model through an intermediate (pivot) table.
     *
     * Example:
     *     User belongs to many Role
     *
     *     Pivot table: role_user
     *     - Foreign key referencing User:  user_id
     *     - Foreign key referencing Role:  role_id
     *
     * The pivot table name must be explicitly provided.
     * Automatic pivot table name resolution is intentionally not implemented
     * to avoid ambiguous or incorrect conventions.
     *
     * If no keys are provided, conventional defaults are used:
     * - $foreignKey        defaults to $this->getForeignKey()
     * - $relatedKey        defaults to $instance->getForeignKey()
     * - $parentKey         defaults to static::primaryKey()
     * - $relatedParentKey  defaults to $instance->primaryKey()
     *
     * @param class-string<\AdaiasMagdiel\Rubik\Model> $related
     *        Fully-qualified related model class name.
     * @param string|null $pivotTable
     *        Name of the intermediate (pivot) table.
     * @param string|null $foreignKey
     *        Foreign key column on the pivot table referencing the parent model.
     * @param string|null $relatedKey
     *        Foreign key column on the pivot table referencing the related model.
     * @param string|null $parentKey
     *        Key column on the parent model.
     * @param string|null $relatedParentKey
     *        Key column on the related model.
     *
     * @return \AdaiasMagdiel\Rubik\Relation\BelongsToMany
     *
     * @throws \InvalidArgumentException
     *         If the pivot table name is not provided.
     */
    public function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignKey = null,
        ?string $relatedKey = null,
        ?string $parentKey = null,
        ?string $relatedParentKey = null
    ): BelongsToMany {
        $instance = new $related();

        if ($pivotTable === null) {
            throw new InvalidArgumentException("Pivot table name must be provided");
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $relatedKey = $relatedKey ?: $instance->getForeignKey();
        $parentKey = $parentKey ?: static::primaryKey();
        $relatedParentKey = $relatedParentKey ?: $instance->primaryKey();

        return new BelongsToMany(
            $instance->newQuery(),
            $this,
            $pivotTable,
            $foreignKey,
            $relatedKey,
            $parentKey,
            $relatedParentKey
        );
    }

    /**
     * Get the conventional foreign key name for this model.
     *
     * By default, the foreign key is generated from the model's base class name:
     *     User      -> "user_id"
     *     BlogPost  -> "blogpost_id" (simple lowercase convention)
     *
     * Note:
     * If you want "blog_post_id" (snake_case), you'll need a different naming strategy.
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return strtolower(basename(str_replace('\\', '/', static::class))) . '_id';
    }

    /**
     * Create a new "clean" query builder instance for the model.
     *
     * This is typically used internally by relationships to start from a fresh query.
     *
     * @return Query
     */
    public function newQuery(): Query
    {
        return static::query();
    }

    /**
     * Manually sets a loaded relationship on the model instance.
     * 
     * This is primarily used by the Eager Loading mechanism to inject
     * related models without triggering a database query via __get().
     *
     * @param string $key   The relationship name.
     * @param mixed  $value The related model instance or collection.
     * @return void
     */
    public function setRelation(string $key, mixed $value): void
    {
        $this->_relationships[$key] = $value;
    }

    /**
     * Sets a value for a model field.
     * 
     * Marks the field as dirty if it exists in the schema definition.
     *
     * @param string $name  The field name.
     * @param mixed  $value The value to set.
     * @return void
     */
    public function __set(string $name, $value): void
    {
        // Allow setting fields defined in fields() or any field during hydration
        $fields = static::fields();
        if (array_key_exists($name, $fields) || !empty($this->_data)) {
            $this->_data[$name] = $value;
            $this->_dirty[$name] = true;
        }
    }

    /**
     * Dynamically retrieve an attribute value or resolve a relationship.
     *
     * Resolution order:
     * 1) If the key exists in the relationship cache, return the cached value.
     * 2) If the key exists in the model's raw attribute data, return the attribute value.
     *    - If the attribute is the primary key, it is normalized to int when numeric.
     * 3) If a method with the same name exists on the model, treat it as a relationship
     *    definition, execute it, cache the resolved results, and return them.
     *
     * This enables a clean API such as:
     *     $user->name        // attribute
     *     $user->posts       // resolved relationship (lazy-loaded and cached)
     *
     * Important:
     * - Relationship methods are expected to return a Relation instance.
     * - The relation is then resolved via getResults() (e.g. array for HasMany, Model|null for BelongsTo),
     *   and the result is cached in $_relationships under the relationship name.
     *
     * @param string $key The attribute name or relationship name being accessed.
     *
     * @return mixed Returns:
     *  - The attribute value if it exists in the model data
     *  - The cached relationship value if already loaded
     *  - The resolved relationship value if a relationship method exists
     *  - null if neither an attribute nor a relationship is found
     *
     * @throws InvalidArgumentException If a relationship method does not return a Relation instance
     *                                  or an unexpected relationship type is encountered.
     */
    public function __get(string $key): mixed
    {
        // 1) Return cached relationship results if available
        if (array_key_exists($key, $this->_relationships)) {
            return $this->_relationships[$key];
        }

        // 2) Return attribute value if present
        if (array_key_exists($key, $this->_data)) {
            $value = $this->_data[$key];

            // Normalize primary key values when possible
            if ($key === static::primaryKey()) {
                return is_numeric($value) ? (int) $value : $value;
            }

            return $value;
        }

        // 3) Attempt to resolve a relationship by method name
        if (method_exists($this, $key)) {
            // Call the relationship definition method (e.g. $user->posts())
            $relation = $this->$key();

            // Relationship methods must return a Relation instance
            if (!$relation instanceof Relation) {
                throw new InvalidArgumentException(
                    sprintf('Relationship "%s" must return an instance of %s.', $key, Relation::class)
                );
            }

            // Resolve results (e.g. array<Model> for HasMany, Model|null for BelongsTo)
            $results = $relation->getResults();

            // Cache resolved relationship results
            $this->setRelation($key, $results);

            return $results;
        }

        return null;
    }

    /**
     * Hydrates the model from database results.
     * Internal use only.
     * 
     * @param array $attributes Raw data from database
     */
    public function hydrate(array $attributes): void
    {
        $this->_data = $attributes;
        $this->_dirty = [];
        $this->exists = true;
    }

    /**
     * Handles dynamic static method calls to the model.
     *
     * Proxies static calls to a new Query Builder instance.
     * Example: User::find(1), User::where(...), or User::active().
     * 
     * @param string $method     The method name being called.
     * @param array  $parameters The method arguments.
     * @return mixed The result of the query builder method.
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return static::query()->$method(...$parameters);
    }

    /**
     * Hook triggered before a model is created or updated.
     */
    protected function beforeSave(): void {}

    /**
     * Hook triggered after a model is successfully created or updated.
     */
    protected function afterSave(): void {}

    /**
     * Hook triggered before a new model is inserted into the database.
     */
    protected function beforeCreate(): void {}

    /**
     * Hook triggered after a new model is successfully inserted into the database.
     */
    protected function afterCreate(): void {}

    /**
     * Hook triggered before an existing model is updated in the database.
     */
    protected function beforeUpdate(): void {}

    /**
     * Hook triggered after an existing model is successfully updated in the database.
     */
    protected function afterUpdate(): void {}

    /**
     * Hook triggered before a model is deleted from the database.
     */
    protected function beforeDelete(): void {}

    /**
     * Hook triggered after a model is successfully deleted from the database.
     */
    protected function afterDelete(): void {}
}
