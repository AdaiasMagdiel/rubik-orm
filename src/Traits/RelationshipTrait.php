<?php

namespace AdaiasMagdiel\Rubik\Traits;

use AdaiasMagdiel\Rubik\Relationship;

trait RelationshipTrait
{
    /**
     * Defines a belongsTo relationship with another model.
     *
     * @param string $related The fully qualified class name of the related model.
     * @param string $foreignKey The foreign key column in the current model's table.
     * @return Relationship The relationship instance.
     */
    public function belongsTo(string $related, string $foreignKey): Relationship
    {
        return new Relationship('belongsTo', static::class, $related, $foreignKey, $this);
    }

    /**
     * Defines a hasMany relationship with another model.
     *
     * @param string $related The fully qualified class name of the related model.
     * @param string $foreignKey The foreign key column in the related model's table.
     * @return Relationship The relationship instance.
     */
    public function hasMany(string $related, string $foreignKey): Relationship
    {
        return new Relationship('hasMany', static::class, $related, $foreignKey, $this);
    }
}
