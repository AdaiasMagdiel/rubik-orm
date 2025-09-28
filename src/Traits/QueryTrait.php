<?php

namespace AdaiasMagdiel\Rubik\Traits;

use AdaiasMagdiel\Rubik\Query;

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

    public static function find(mixed $id): ?self
    {
        return static::query()->where(static::primaryKey(), $id)->first();
    }

    public static function first(): ?self
    {
        return static::query()->first();
    }

    public static function all(): array
    {
        return static::query()->all();
    }

    public static function paginate(int $page, int $perPage): array
    {
        return static::query()->paginate($page, $perPage);
    }
}
