<?php

namespace AdaiasMagdiel\Rubik\Trait\Model;

use AdaiasMagdiel\Rubik\Query;
use InvalidArgumentException;

trait QueryTrait
{
    /**
     * Creates a new query builder instance for the model.
     *
     * @return Query A query builder instance configured for the model.
     */
    public static function query(): Query
    {
        return (new Query())->setModel(static::class);
    }

    /**
     * Finds a model instance by its primary key.
     *
     * @param mixed $id The primary key value.
     * @return ?self The model instance or null if not found.
     */
    public static function find(mixed $id): ?self
    {
        return static::query()->where(static::primaryKey(), $id)->first();
    }

    /**
     * Retrieves the first model instance.
     *
     * @return ?self The first model instance or null if none exist.
     */
    public static function first(): ?self
    {
        return static::query()->first();
    }

    /**
     * Retrieves all model instances.
     *
     * @return array Array of model instances.
     */
    public static function all(): array
    {
        return static::query()->all();
    }

    /**
     * Retrieves paginated model instances.
     *
     * @param int $page The page number (1-based).
     * @param int $perPage Number of records per page.
     * @return array Paginated results with data, total, per_page, current_page, and last_page.
     */
    public static function paginate(int $page, int $perPage): array
    {
        return static::query()->paginate($page, $perPage);
    }

    /**
     * Defines a belongsTo relationship.
     *
     * @param string $related The related model class.
     * @param string $foreignKey The foreign key on the current model's table.
     * @param string $ownerKey The primary key on the related model's table (default: 'id').
     * @return Query A query builder instance for the related model.
     */
    public function belongsTo(string $related, string $foreignKey, string $ownerKey = 'id'): Query
    {
        if (!class_exists($related)) {
            throw new InvalidArgumentException("Related model class {$related} does not exist.");
        }

        $relatedTable = $related::getTableName();
        $query = (new Query())->setModel($related);
        $query->join(
            $relatedTable,
            sprintf('%s.%s', static::getTableName(), $foreignKey),
            '=',
            sprintf('%s.%s', $relatedTable, $ownerKey)
        );

        if (isset($this->_data[$foreignKey])) {
            $query->where(sprintf('%s.%s', $relatedTable, $ownerKey), $this->_data[$foreignKey]);
        }

        return $query;
    }

    /**
     * Defines a hasOne relationship.
     *
     * @param string $related The related model class.
     * @param string $foreignKey The foreign key on the related model's table.
     * @param string $localKey The primary key on the current model's table (default: 'id').
     * @return Query A query builder instance for the related model.
     */
    public function hasOne(string $related, string $foreignKey, string $localKey = 'id'): Query
    {
        if (!class_exists($related)) {
            throw new InvalidArgumentException("Related model class {$related} does not exist.");
        }

        $relatedTable = $related::getTableName();
        $query = (new Query())->setModel($related);
        $query->where(sprintf('%s.%s', $relatedTable, $foreignKey), $this->_data[$localKey] ?? null);

        return $query;
    }

    /**
     * Defines a hasMany relationship.
     *
     * @param string $related The related model class.
     * @param string $foreignKey The foreign key on the related model's table.
     * @param string $localKey The primary key on the current model's table (default: 'id').
     * @return Query A query builder instance for the related models.
     */
    public function hasMany(string $related, string $foreignKey, string $localKey = 'id'): Query
    {
        if (!class_exists($related)) {
            throw new InvalidArgumentException("Related model class {$related} does not exist.");
        }

        $relatedTable = $related::getTableName();
        $query = (new Query())->setModel($related);
        $query->where(sprintf('%s.%s', $relatedTable, $foreignKey), $this->_data[$localKey] ?? null);

        return $query;
    }

    /**
     * Defines a belongsToMany relationship.
     *
     * @param string $related The related model class.
     * @param string $pivotTable The pivot table name.
     * @param string $foreignKey The foreign key for the current model in the pivot table.
     * @param string $relatedKey The foreign key for the related model in the pivot table.
     * @param string $localKey The primary key on the current model's table (default: 'id').
     * @param string $relatedOwnerKey The primary key on the related model's table (default: 'id').
     * @return Query A query builder instance for the related models.
     */
    public function belongsToMany(
        string $related,
        string $pivotTable,
        string $foreignKey,
        string $relatedKey,
        string $localKey = 'id',
        string $relatedOwnerKey = 'id'
    ): Query {
        if (!class_exists($related)) {
            throw new InvalidArgumentException("Related model class {$related} does not exist.");
        }

        $relatedTable = $related::getTableName();
        $query = (new Query())->setModel($related);
        $query->join(
            $pivotTable,
            sprintf('%s.%s', $pivotTable, $relatedKey),
            '=',
            sprintf('%s.%s', $relatedTable, $relatedOwnerKey)
        )->where(
            sprintf('%s.%s', $pivotTable, $foreignKey),
            $this->_data[$localKey] ?? null
        );

        return $query;
    }
}
