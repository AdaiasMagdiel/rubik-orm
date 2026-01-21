<?php

namespace AdaiasMagdiel\Rubik\Relation;

use AdaiasMagdiel\Rubik\Query;
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Relation;

class BelongsToMany extends Relation
{
    /**
     * The name of the pivot (intermediate) table.
     *
     * Example: "role_user"
     */
    protected string $pivotTable;

    /**
     * The foreign key on the pivot table that references the parent model.
     *
     * Example: "user_id"
     */
    protected string $foreignKey;

    /**
     * The foreign key on the pivot table that references the related model.
     *
     * Example: "role_id"
     */
    protected string $relatedKey;

    /**
     * The key on the parent model.
     *
     * Example: "id" on the users table.
     */
    protected string $parentKey;

    /**
     * The key on the related model.
     *
     * Example: "id" on the roles table.
     */
    protected string $relatedParentKey;

    /**
     * Create a new BelongsToMany relationship instance.
     *
     * @param Query  $query             The query builder for the related model.
     * @param Model  $parent            The parent model instance.
     * @param string $pivotTable        The name of the pivot table.
     * @param string $foreignKey        Foreign key on the pivot table referencing the parent model.
     * @param string $relatedKey        Foreign key on the pivot table referencing the related model.
     * @param string $parentKey         Key on the parent model.
     * @param string $relatedParentKey  Key on the related model.
     */
    public function __construct(
        Query $query,
        Model $parent,
        string $pivotTable,
        string $foreignKey,
        string $relatedKey,
        string $parentKey,
        string $relatedParentKey
    ) {
        parent::__construct($query, $parent);

        $this->pivotTable       = $pivotTable;
        $this->foreignKey       = $foreignKey;
        $this->relatedKey       = $relatedKey;
        $this->parentKey        = $parentKey;
        $this->relatedParentKey = $relatedParentKey;
    }

    /**
     * Apply the base constraints for the BelongsToMany relationship.
     *
     * This builds an INNER JOIN between the related table and the pivot table,
     * constraining the results to the parent model.
     *
     * Conceptual SQL:
     *     SELECT related.*
     *     FROM related
     *     INNER JOIN pivot
     *         ON pivot.related_id = related.id
     *     WHERE pivot.parent_id = ?
     *
     * @return void
     */
    public function addConstraints(): void
    {
        $this->query
            ->join(
                $this->pivotTable,
                $this->pivotTable . '.' . $this->relatedKey,
                '=',
                $this->query->getTable() . '.' . $this->relatedParentKey
            )
            ->where(
                $this->pivotTable . '.' . $this->foreignKey,
                $this->parent->{$this->parentKey}
            );
    }

    /**
     * Resolve the relationship and return all related models.
     *
     * @return array<int, Model> An array of related model instances.
     */
    public function getResults(): array
    {
        return $this->get();
    }

    /**
     * Get the name of the pivot (intermediate) table.
     *
     * @return string The pivot table name.
     */
    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    /**
     * Get the foreign key on the pivot table that references the parent model.
     *
     * @return string The foreign key column name.
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the foreign key on the pivot table that references the related model.
     *
     * @return string The related key column name.
     */
    public function getRelatedKey(): string
    {
        return $this->relatedKey;
    }

    /**
     * Get the key on the parent model.
     *
     * @return string The parent key column name.
     */
    public function getParentKey(): string
    {
        return $this->parentKey;
    }

    /**
     * Get the key on the related model.
     *
     * @return string The related parent key column name.
     */
    public function getRelatedParentKey(): string
    {
        return $this->relatedParentKey;
    }
}
