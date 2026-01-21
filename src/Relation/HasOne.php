<?php

namespace AdaiasMagdiel\Rubik\Relation;

use AdaiasMagdiel\Rubik\Query;
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Relation;

class HasOne extends Relation
{
    /**
     * The foreign key on the related model.
     *
     * Example: "user_id" on the phones table.
     */
    protected string $foreignKey;

    /**
     * The local key on the parent model.
     *
     * Example: "id" on the users table.
     */
    protected string $localKey;

    /**
     * Create a new HasOne relationship instance.
     *
     * @param Query  $query      The query builder for the related model.
     * @param Model  $parent     The parent (owning) model instance.
     * @param string $foreignKey The foreign key column on the related table.
     * @param string $localKey   The local key column on the parent model.
     */
    public function __construct(
        Query $query,
        Model $parent,
        string $foreignKey,
        string $localKey
    ) {
        parent::__construct($query, $parent);

        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
    }

    /**
     * Apply the base constraints for the HasOne relationship.
     *
     * This adds a "where" clause that matches the related model's
     * foreign key to the parent model's local key.
     *
     * Example SQL:
     *     SELECT * FROM phones WHERE user_id = 1
     *
     * @return void
     */
    public function addConstraints(): void
    {
        $value = $this->parent->{$this->localKey};

        $this->query->where($this->foreignKey, $value);
    }

    /**
     * Resolve the relationship and return a single related model.
     *
     * Overrides the default behavior to return only the first
     * matching record instead of a collection.
     *
     * @return Model|null The related model instance or null if none exists.
     */
    public function getResults(): ?Model
    {
        $this->addConstraints();

        return $this->query->first();
    }

    /**
     * Get the foreign key used by the HasMany relationship.
     *
     * This key exists on the related model and references
     * the parent model's local key.
     *
     * @return string The foreign key column name.
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the local key used by the HasMany relationship.
     *
     * This key exists on the parent model and is typically
     * the primary key.
     *
     * @return string The local key column name.
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }
}
