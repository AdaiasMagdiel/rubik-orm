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
}
