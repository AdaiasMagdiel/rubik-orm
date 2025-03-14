<?php

namespace AdaiasMagdiel\Rubik;

use InvalidArgumentException;
use LogicException;
use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Fluent SQL query builder class.
 */
class Query
{
    private array $select = [];
    private array $where = [];
    private int $limit = -1;
    private int $offset = -1;
    private array $bindings = [];
    private string $operation = "SELECT";
    private string $model;
    private string $table;

    /**
     * Sets the table name for the query.
     *
     * @param string $table Table name
     * @return self
     */
    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Sets the model class and automatically configures the table.
     *
     * @param string $model Model class name
     * @return self
     * @throws RuntimeException If the model class does not exist or lacks getTableName()
     */
    public function setModel(string $model): self
    {
        if (!class_exists($model)) {
            throw new RuntimeException("Model class {$model} does not exist");
        }

        if (!method_exists($model, 'getTableName')) {
            throw new RuntimeException("Model {$model} must implement static method getTableName()");
        }

        $this->model = $model;
        $this->table = $model::getTableName();
        return $this;
    }

    /**
     * Adds fields to select (automatically includes primary key).
     *
     * @param string|array $fields Fields to select
     * @return self
     */
    public function select(string|array $fields = "*"): self
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $pk = $this->model::primaryKey();
        $this->select = array_unique(array_merge(
            $this->select,
            array_merge([$pk], $fields)
        ));

        return $this;
    }

    /**
     * Sets the operation to DELETE.
     *
     * @return self
     */
    public function delete(): self
    {
        $this->operation = "DELETE";
        return $this;
    }

    /**
     * Adds a WHERE condition with AND conjunction.
     *
     * @param string $key Column name
     * @param mixed $value Value
     * @param string $op Operator (default '=')
     * @return self
     */
    public function where(string $key, mixed $value, string $op = "="): self
    {
        $this->addCondition($key, $value, $op, 'AND');
        return $this;
    }

    /**
     * Adds a WHERE condition with OR conjunction.
     *
     * @param string $key Column name
     * @param mixed $value Value
     * @param string $op Operator (default '=')
     * @return self
     */
    public function whereOr(string $key, mixed $value, string $op = "="): self
    {
        $this->addCondition($key, $value, $op, 'OR');
        return $this;
    }

    /**
     * Adds a WHERE IN condition.
     *
     * @param string $key Column name
     * @param array $values Values array
     * @return self
     * @throws InvalidArgumentException If values array is empty
     */
    public function whereIn(string $key, array $values): self
    {
        if (empty($values)) {
            throw new InvalidArgumentException("whereIn requires a non-empty array");
        }

        $placeholders = array_map(
            fn($i) => $param = ":{$key}_in_{$i}",
            array_keys($values)
        );

        foreach ($values as $i => $value) {
            $this->bindings[":{$key}_in_{$i}"] = $value;
        }

        $this->addCompositeCondition(
            $key,
            "IN (" . implode(', ', $placeholders) . ")",
            'AND'
        );

        return $this;
    }

    /**
     * Sets the result limit.
     *
     * @param int $limit Number of records
     * @return self
     * @throws InvalidArgumentException If limit is invalid
     */
    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException("Limit must be a non-negative integer");
        }

        $this->limit = $limit;
        return $this;
    }

    /**
     * Sets the result offset.
     *
     * @param int $offset Number of records to skip
     * @return self
     * @throws LogicException If offset is set without a limit
     */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException("Offset must be a non-negative integer");
        }

        if ($this->limit === -1) {
            throw new LogicException("Cannot set offset without first setting a limit");
        }

        $this->offset = $offset;
        return $this;
    }

    /**
     * Executes the query and returns all results.
     *
     * @return array Array of model instances
     * @throws RuntimeException If execution fails
     */
    public function all(): array
    {
        $statement = $this->executeStatement();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrateModel'], $results ?: []);
    }

    /**
     * Executes the query and returns the first result.
     *
     * @return object|null Model instance or null
     * @throws RuntimeException If execution fails
     */
    public function first(): ?object
    {
        $statement = $this->executeStatement();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->hydrateModel($result) : null;
    }

    /**
     * Executes a write operation (DELETE/UPDATE).
     *
     * @return bool Operation success
     * @throws RuntimeException If execution fails
     */
    public function exec(): bool
    {
        return $this->executeStatement()->rowCount() > 0;
    }

    /**
     * Builds and executes the SQL statement.
     */
    private function executeStatement(): PDOStatement
    {
        $statement = $this->prepareStatement();
        $statement->execute($this->bindings);

        if ($statement->errorCode() !== '00000') {
            $error = $statement->errorInfo();
            throw new RuntimeException("Query execution failed: {$error[2]}");
        }

        return $statement;
    }

    /**
     * Prepares the PDO statement.
     */
    private function prepareStatement(): PDOStatement
    {
        $sql = $this->buildSQL();
        $pdo = Rubik::getConn();

        $statement = $pdo->prepare($sql);
        if (!$statement) {
            $error = $pdo->errorInfo();
            throw new RuntimeException("Failed to prepare query: {$error[2]}");
        }

        return $statement;
    }

    /**
     * Builds the full SQL statement.
     */
    private function buildSQL(): string
    {
        $this->validateQueryState();

        $clauses = [
            $this->buildOperationClause(),
            $this->buildWhereClause(),
            $this->buildLimitClause(),
            $this->buildOffsetClause()
        ];

        return implode(' ', array_filter($clauses));
    }

    /**
     * Validates the current query state.
     */
    private function validateQueryState(): void
    {
        if (empty($this->table)) {
            throw new RuntimeException("Table name must be set using setTable() or setModel()");
        }
    }

    /**
     * Adds a condition to the WHERE clause.
     */
    private function addCondition(string $key, mixed $value, string $op, string $conjunction): void
    {
        $placeholder = ":{$key}_" . count($this->bindings);
        $this->addCompositeCondition(
            "{$key} {$op} {$placeholder}",
            $conjunction
        );
        $this->bindings[$placeholder] = $value;
    }

    /**
     * Adds a composite condition to the WHERE clause.
     */
    private function addCompositeCondition(string $condition, string $conjunction): void
    {
        if (empty($this->where)) {
            $this->where[] = "WHERE {$condition}";
        } else {
            $this->where[] = "{$conjunction} {$condition}";
        }
    }

    /**
     * Creates a model instance with query results.
     */
    private function hydrateModel(array $data): object
    {
        $model = new $this->model();
        foreach ($data as $key => $value) {
            $model->__set($key, $value);
        }
        return $model;
    }

    // SQL clause builders
    private function buildOperationClause(): string
    {
        if ($this->operation === 'SELECT') {
            $fields = empty($this->select) ? '*' : implode(', ', $this->select);
            return "SELECT {$fields} FROM {$this->table}";
        }

        return "DELETE FROM {$this->table}";
    }

    private function buildWhereClause(): string
    {
        return implode(' ', $this->where);
    }

    private function buildLimitClause(): string
    {
        return $this->limit > -1 ? "LIMIT {$this->limit}" : '';
    }

    private function buildOffsetClause(): string
    {
        return $this->offset > -1 ? "OFFSET {$this->offset}" : '';
    }
}
