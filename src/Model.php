<?php

namespace AdaiasMagdiel\Rubik;

use AdaiasMagdiel\Rubik\Trait\Model as Traits;
use InvalidArgumentException;
use JsonSerializable;

abstract class Model implements JsonSerializable
{
    use Traits\CrudTrait;
    use Traits\QueryTrait;
    use Traits\SchemaTrait;
    use Traits\SerializationTrait;

    /** @var string The database table name for the model. */
    protected static string $table = '';

    /** @var array Associative array of column names and their values for the model instance. */
    protected array $_data = [];

    /** @var array Associative array of columns that have been modified since the last save. */
    protected array $_dirty = [];

    /** @var array Cache for loaded relationship results. */
    protected array $_relationships = [];

    /** @var bool Indicates if the model exists in the database. */
    public bool $exists = false;

    /**
     * Defines the relationships for the model.
     *
     * Subclasses should override this to specify relationships like belongsTo, hasOne, hasMany, or belongsToMany.
     *
     * @return array Associative array of relationship names and their definitions.
     */
    public static function relationships(): array
    {
        return [];
    }

    public function setRelation(string $key, mixed $value): void
    {
        $this->_relationships[$key] = $value;
    }

    /**
     * Sets a value for a model field, marking it as dirty if it exists in the schema.
     *
     * @param string $name The field name.
     * @param mixed $value The value to set.
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
     * Retrieves a field value or resolves a relationship.
     *
     * If the key is a field, returns its value. If it is a relationship defined in relationships(),
     * executes and caches the relationship results.
     *
     * @param string $key The field name or relationship name.
     * @return mixed The field value, relationship results, or null if not found.
     */
    public function __get(string $key): mixed
    {
        // Return cached relationship results if available
        if (array_key_exists($key, $this->_relationships)) {
            return $this->_relationships[$key];
        }

        // Check if the key is a field
        if (array_key_exists($key, $this->_data)) {
            $value = $this->_data[$key];

            if ($key === static::primaryKey()) {
                return is_numeric($value) ? (int)$value : $value;
            }

            return $value;
        }

        // Check if the key is a relationship
        $relationships = static::relationships();
        if (array_key_exists($key, $relationships)) {
            $rel = $relationships[$key];
            $type = $rel['type'] ?? '';
            $query = null;

            switch ($type) {
                case 'belongsTo':
                    $query = $this->belongsTo(
                        $rel['related'],
                        $rel['foreignKey'] ?? $key . '_id',
                        $rel['ownerKey'] ?? 'id'
                    );
                    $this->_relationships[$key] = $query->first();
                    break;
                case 'hasOne':
                    $query = $this->hasOne(
                        $rel['related'],
                        $rel['foreignKey'] ?? static::primaryKey(),
                        $rel['localKey'] ?? 'id'
                    );
                    $this->_relationships[$key] = $query->first();
                    break;
                case 'hasMany':
                    $query = $this->hasMany(
                        $rel['related'],
                        $rel['foreignKey'] ?? static::primaryKey(),
                        $rel['localKey'] ?? 'id'
                    );
                    $this->_relationships[$key] = $query->all();
                    break;
                case 'belongsToMany':
                    $query = $this->belongsToMany(
                        $rel['related'],
                        $rel['pivotTable'],
                        $rel['foreignKey'] ?? static::primaryKey(),
                        $rel['relatedKey'] ?? $rel['related']::primaryKey(),
                        $rel['localKey'] ?? 'id',
                        $rel['relatedOwnerKey'] ?? 'id'
                    );
                    $this->_relationships[$key] = $query->all();
                    break;
                default:
                    throw new InvalidArgumentException("Unknown relationship type: {$type}");
            }

            return $this->_relationships[$key];
        }

        return null;
    }

    protected function beforeSave(): void {}
    protected function afterSave(): void {}
    protected function beforeCreate(): void {}
    protected function afterCreate(): void {}
    protected function beforeUpdate(): void {}
    protected function afterUpdate(): void {}
    protected function beforeDelete(): void {}
    protected function afterDelete(): void {}
}
