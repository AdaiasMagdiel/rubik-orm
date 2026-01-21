<?php

namespace AdaiasMagdiel\Rubik;

abstract class Relation
{
    /**
     * The query builder instance used to resolve the relationship.
     */
    protected Query $query;

    /**
     * The parent model instance that owns this relationship.
     */
    protected Model $parent;

    /**
     * Create a new relationship instance.
     *
     * @param Query $query  The base query builder for the related model.
     * @param Model $parent The parent model instance.
     */
    public function __construct(Query $query, Model $parent)
    {
        $this->query  = $query;
        $this->parent = $parent;
    }

    /**
     * Apply the base constraints for the relationship.
     *
     * This method is responsible for adding relationship-specific
     * constraints to the query (e.g. "user_id = 1").
     *
     * It is automatically called before executing the query.
     *
     * @return void
     */
    abstract public function addConstraints(): void;

    /**
     * Get the underlying query builder instance.
     *
     * This allows further query customization or direct access
     * to the query object when needed.
     *
     * @return Query
     */
    public function getQuery(): Query
    {
        return $this->query;
    }

    /**
     * Proxy method calls to the underlying query builder.
     *
     * This enables fluent chaining directly on the relationship,
     * for example:
     *
     *     $user->posts()->where('published', true)->orderBy('created_at');
     *
     * If the proxied method returns the query builder itself,
     * the relation instance is returned instead to preserve fluency.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $result = $this->query->$method(...$parameters);

        // If the query returns itself, return the relation to keep fluent chaining
        if ($result instanceof Query) {
            return $this;
        }

        return $result;
    }

    /**
     * Execute the relationship query and return all matching models.
     *
     * @return array<int, Model>
     */
    public function get(): array
    {
        $this->addConstraints();

        return $this->query->all();
    }

    /**
     * Execute the relationship query and return the first matching model.
     *
     * @return Model|null
     */
    public function first(): ?Model
    {
        $this->addConstraints();

        return $this->query->first();
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    abstract public function getResults(): mixed;
}
