<?php

namespace AdaiasMagdiel\Rubik;

use AdaiasMagdiel\Rubik\Traits;
use JsonSerializable;

abstract class Model implements JsonSerializable
{
    use Traits\CrudTrait;
    use Traits\QueryTrait;
    use Traits\SchemaTrait;
    use Traits\RelationshipTrait;
    use Traits\SerializationTrait;

    /** @var string The database table name for the model. */
    protected static string $table = '';

    /** @var array Associative array of column names and their values for the model instance. */
    protected array $_data = [];

    /** @var array Associative array of columns that have been modified since the last save. */
    protected array $_dirty = [];

    /** @var array Associative array of cached relationship results. */
    protected array $relationships = [];

    /**
     * Sets a value for a model field, marking it as dirty if it exists in the schema.
     *
     * @param string $key The field name.
     * @param mixed $value The value to set.
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        if ($this->hasField($key)) {
            $this->_data[$key] = $value;
            $this->_dirty[$key] = true;
        }
    }

    /**
     * Retrieves a field value or resolves a relationship.
     *
     * If the key is a field, returns its value. If it is a relationship method,
     * executes and caches the relationship results.
     *
     * @param string $key The field name or relationship method name.
     * @return mixed The field value, relationship results, or null if not found.
     */
    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->_data[$key];
        }

        if (method_exists($this, $key)) {
            $relationship = $this->$key();
            return $this->relationships[$key] = $relationship->getResults();
        }

        return null;
    }
}
