<?php

namespace AdaiasMagdiel\Rubik\Relation;

use AdaiasMagdiel\Rubik\Query;
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Relation;

class BelongsTo extends Relation
{
    /**
     * The foreign key on the parent (child) model.
     *
     * Example: "user_id" on the posts table.
     */
    protected string $foreignKey;

    /**
     * The owner (primary) key on the related model.
     *
     * Example: "id" on the users table.
     */
    protected string $ownerKey;

    /**
     * Create a new BelongsTo relationship instance.
     *
     * @param Query  $query      The query builder for the related (owner) model.
     * @param Model  $parent     The child model instance that holds the foreign key.
     * @param string $foreignKey The foreign key column on the child model.
     * @param string $ownerKey   The owner (primary) key column on the related model.
     */
    public function __construct(
        Query $query,
        Model $parent,
        string $foreignKey,
        string $ownerKey
    ) {
        parent::__construct($query, $parent);

        $this->foreignKey = $foreignKey;
        $this->ownerKey   = $ownerKey;
    }

    /**
     * Apply the base constraints for the BelongsTo relationship.
     *
     * This adds a "where" constraint matching the related model's
     * owner key to the value stored in the parent model's foreign key.
     *
     * Example:
     *     A Post belongs to a User
     *     - Foreign key on Post:  user_id
     *     - Owner key on User:    id
     *
     * Example SQL:
     *     SELECT * FROM users WHERE id = 5
     *
     * @return void
     */
    public function addConstraints(): void
    {
        $value = $this->parent->{$this->foreignKey};

        $this->query->where($this->ownerKey, $value);
    }

    /**
     * Resolve the relationship and return a single related model.
     *
     * This method executes the relationship query and returns
     * the first matching record, or null if no related model exists.
     *
     * @return Model|null The related model instance or null.
     */
    public function getResults(): ?Model
    {
        return $this->first();
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
    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }
}
