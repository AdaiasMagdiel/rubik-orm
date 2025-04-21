<?php

namespace AdaiasMagdiel\Rubik;

use RuntimeException;

/**
 * Represents a relationship between two models in the Rubik ORM.
 * Supports 'belongsTo' and 'hasMany' relationship types.
 */
class Relationship
{
    /** @var string The type of relationship ('belongsTo' or 'hasMany'). */
    private string $type;

    /** @var string The fully qualified class name of the parent model. */
    private string $parentModel;

    /** @var string The fully qualified class name of the related model. */
    private string $relatedModel;

    /** @var string The foreign key column name in the relationship. */
    private string $foreignKey;

    /** @var object|null The instance of the parent model, required for certain operations. */
    private ?object $parentInstance;

    /**
     * Constructs a new Relationship instance.
     *
     * @param string $type The type of relationship ('belongsTo' or 'hasMany').
     * @param string $parentModel The fully qualified class name of the parent model.
     * @param string $relatedModel The fully qualified class name of the related model.
     * @param string $foreignKey The foreign key column name used in the relationship.
     * @param object|null $parentInstance The instance of the parent model, if available.
     */
    public function __construct(
        string $type,
        string $parentModel,
        string $relatedModel,
        string $foreignKey,
        ?object $parentInstance = null
    ) {
        $this->type = $type;
        $this->parentModel = $parentModel;
        $this->relatedModel = $relatedModel;
        $this->foreignKey = $foreignKey;
        $this->parentInstance = $parentInstance;
    }

    /**
     * Retrieves the results of the relationship query.
     *
     * For 'belongsTo', returns a single related model instance or null.
     * For 'hasMany', returns an array of related model instances.
     *
     * @return mixed The related model instance(s) or null for 'belongsTo' if no match is found.
     * @throws RuntimeException If the relationship type is unsupported or if a parent instance is required but not provided.
     */
    public function getResults()
    {
        return match ($this->type) {
            'belongsTo' => $this->getBelongsToResults(),
            'hasMany' => $this->relatedModel::query()
                ->where($this->foreignKey, $this->parentInstance->__get($this->parentModel::primaryKey()))
                ->all(),
            default => throw new RuntimeException("Unsupported relationship type: {$this->type}"),
        };
    }

    /**
     * Retrieves the result for a 'belongsTo' relationship.
     *
     * Queries the related model using the foreign key value from the parent instance.
     *
     * @return object|null The related model instance, or null if the foreign key value is null or no match is found.
     * @throws RuntimeException If the parent instance is not provided.
     */
    private function getBelongsToResults()
    {
        if (!$this->parentInstance) {
            throw new RuntimeException('Parent instance required for belongsTo relationship');
        }

        $foreignKeyValue = $this->parentInstance->__get($this->foreignKey);
        if ($foreignKeyValue === null) {
            return null;
        }

        return $this->relatedModel::query()
            ->where($this->relatedModel::primaryKey(), $foreignKeyValue)
            ->first();
    }
}
