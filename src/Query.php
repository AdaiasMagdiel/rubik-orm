<?php

namespace AdaiasMagdiel\Rubik;

use PDO;
use PDOStatement;
use RuntimeException;
use InvalidArgumentException;

/**
 * Query builder for constructing and executing SQL queries in the Rubik ORM.
 * Supports SELECT, UPDATE, DELETE operations with conditions, joins, and model hydration.
 */
class Query
{
    /** @var string The fully qualified class name of the associated model, if any. */
    private string $model = '';

    /** @var string The database table name to query. */
    private string $table = '';

    /** @var string The SQL operation to perform (e.g., 'SELECT', 'UPDATE', 'DELETE'). */
    private string $operation = 'SELECT';

    /** @var array List of fields to select in the query. */
    private array $select = [];

    /** @var array List of WHERE conditions for the query. */
    private array $where = [];

    /** @var array Associative array of parameter bindings for the query. */
    private array $bindings = [];

    /** @var array List of ORDER BY clauses for the query. */
    private array $orderBy = [];

    /** @var array List of GROUP BY columns for the query. */
    private array $groupBy = [];

    /** @var array List of HAVING conditions for the query. */
    private array $having = [];

    /** @var array List of JOIN clauses for the query. */
    private array $joins = [];

    /** @var int The maximum number of rows to return (-1 for no limit). */
    private int $limit = -1;

    /** @var int The number of rows to skip in the result set (-1 for no offset). */
    private int $offset = -1;

    /**
     * Sets the table name for the query.
     *
     * @param string $table The name of the database table.
     * @return self Returns the query instance for method chaining.
     */
    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Sets the model class for the query and derives the table name.
     *
     * @param string $model The fully qualified class name of the model.
     * @return self Returns the query instance for method chaining.
     * @throws RuntimeException If the model class does not exist.
     */
    public function setModel(string $model): self
    {
        if (!class_exists($model)) {
            throw new RuntimeException("Model class {$model} does not exist");
        }

        $this->model = $model;
        $this->table = $model::getTableName();
        return $this;
    }

    /**
     * Specifies the fields to select in the query.
     *
     * If specific fields are provided, the primary key is automatically included.
     * Use '*' to select all fields.
     *
     * @param string|array $fields The field(s) to select, as a string or array.
     * @return self Returns the query instance for method chaining.
     */
    public function select(string|array $fields = '*'): self
    {
        $fields = is_string($fields) ? [$fields] : $fields;
        if ($fields !== ['*']) {
            $pk = $this->model ? $this->model::primaryKey() : 'id';
            // Qualify primary key with table name
            $qualifiedPk = $this->table . '.' . $pk;
            $this->select = array_unique(array_merge($this->select, [$qualifiedPk], $fields));
        } else {
            $this->select = ['*'];
        }
        return $this;
    }

    /**
     * Adds a WHERE condition to the query with AND conjunction.
     *
     * @param string $key The column name or expression to filter on.
     * @param mixed $value The value to compare against.
     * @param string $op The comparison operator (default: '=').
     * @return self Returns the query instance for method chaining.
     */
    public function where(string $key, mixed $value, string $op = '='): self
    {
        $this->addCondition($key, $value, $op, 'AND');
        return $this;
    }

    /**
     * Adds a WHERE condition to the query with OR conjunction.
     *
     * @param string $key The column name or expression to filter on.
     * @param mixed $value The value to compare against.
     * @param string $op The comparison operator (default: '=').
     * @return self Returns the query instance for method chaining.
     */
    public function orWhere(string $key, mixed $value, string $op = '='): self
    {
        $this->addCondition($key, $value, $op, 'OR');
        return $this;
    }

    /**
     * Adds a WHERE IN condition to the query.
     *
     * @param string $key The column name to filter on.
     * @param array $values The array of values to include in the IN clause.
     * @return self Returns the query instance for method chaining.
     * @throws InvalidArgumentException If the values array is empty.
     */
    public function whereIn(string $key, array $values): self
    {
        if (empty($values)) {
            throw new InvalidArgumentException('whereIn requires a non-empty array');
        }

        $placeholders = [];
        foreach ($values as $i => $value) {
            $placeholder = ":{$key}_in_{$i}";
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $value;
        }

        $this->where[] = sprintf(
            '%s IN (%s)',
            $key,
            implode(', ', $placeholders)
        );
        return $this;
    }

    /**
     * Adds a JOIN clause to the query.
     *
     * @param string $table The table to join with.
     * @param string $first The first column or expression in the JOIN condition.
     * @param string $operator The comparison operator (e.g., '=').
     * @param string $second The second column or expression in the JOIN condition.
     * @param string $type The type of join ('INNER', 'LEFT', 'RIGHT') (default: 'INNER').
     * @return self Returns the query instance for method chaining.
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            strtoupper($type),
            $table,
            $first,
            $operator,
            $second
        );
        return $this;
    }

    /**
     * Adds a LEFT JOIN clause to the query.
     *
     * @param string $table The table to join with.
     * @param string $first The first column or expression in the JOIN condition.
     * @param string $operator The comparison operator (e.g., '=').
     * @param string $second The second column or expression in the JOIN condition.
     * @return self Returns the query instance for method chaining.
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Adds a RIGHT JOIN clause to the query.
     *
     * @param string $table The table to join with.
     * @param string $first The first column or expression in the JOIN condition.
     * @param string $operator The comparison operator (e.g., '=').
     * @param string $second The second column or expression in the JOIN condition.
     * @return self Returns the query instance for method chaining.
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Adds an ORDER BY clause to the query.
     *
     * @param string $column The column to order by.
     * @param string $direction The sort direction ('ASC' or 'DESC') (default: 'ASC').
     * @return self Returns the query instance for method chaining.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = sprintf('%s %s', $column, strtoupper($direction));
        return $this;
    }

    /**
     * Adds a GROUP BY clause to the query.
     *
     * @param string|array $columns The column(s) to group by, as a string or array.
     * @return self Returns the query instance for method chaining.
     */
    public function groupBy(string|array $columns): self
    {
        $columns = is_string($columns) ? [$columns] : $columns;
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    /**
     * Adds a HAVING condition to the query.
     *
     * @param string $condition The HAVING condition to apply.
     * @return self Returns the query instance for method chaining.
     */
    public function having(string $condition): self
    {
        $this->having[] = $condition;
        return $this;
    }

    /**
     * Sets the maximum number of rows to return.
     *
     * @param int $limit The number of rows to limit the result to.
     * @return self Returns the query instance for method chaining.
     * @throws InvalidArgumentException If the limit is negative.
     */
    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be non-negative');
        }
        $this->limit = $limit;
        return $this;
    }

    /**
     * Sets the number of rows to skip in the result set.
     *
     * @param int $offset The number of rows to skip.
     * @return self Returns the query instance for method chaining.
     * @throws InvalidArgumentException If the offset is negative.
     */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be non-negative');
        }
        $this->offset = $offset;
        return $this;
    }

    /**
     * Sets the query operation to DELETE.
     *
     * @return self Returns the query instance for method chaining.
     */
    public function delete(): self
    {
        $this->operation = 'DELETE';
        return $this;
    }

    /**
     * Executes an UPDATE query with the specified data.
     *
     * @param array $data Associative array of column names and values to update.
     * @return bool True if the update was successful, false otherwise.
     */
    public function update(array $data): bool
    {
        $this->operation = 'UPDATE';
        $sets = [];
        foreach ($data as $key => $value) {
            $placeholder = ":{$key}_update";
            $sets[] = sprintf('%s = %s', $key, $placeholder);
            $this->bindings[$placeholder] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s %s',
            $this->table,
            implode(', ', $sets),
            $this->buildWhereClause()
        );

        $stmt = DatabaseConnection::getConnection()->prepare($sql);
        return $stmt->execute($this->bindings);
    }

    /**
     * Executes the query and returns all results, optionally hydrated as model instances.
     *
     * @return array Array of results, either as associative arrays or model instances.
     */
    public function all(): array
    {
        $stmt = $this->executeStatement();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->hydrateModels($results);
    }

    /**
     * Executes the query and returns the first result, optionally hydrated as a model instance.
     *
     * @return object|null The first result as a model instance or object, or null if no results.
     */
    public function first(): ?object
    {
        $this->limit(1);
        $stmt = $this->executeStatement();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $this->hydrateModel($result) : null;
    }

    /**
     * Executes the query and returns whether it affected any rows.
     *
     * @return bool True if the query affected at least one row, false otherwise.
     */
    public function exec(): bool
    {
        return $this->executeStatement()->rowCount() > 0;
    }

    /**
     * Returns the generated SQL query string.
     *
     * @return string The SQL query string.
     */
    public function getSql(): string
    {
        return $this->buildSql();
    }

    /**
     * Executes the query and returns the prepared statement.
     *
     * @return PDOStatement The executed PDO statement.
     * @throws RuntimeException If the query preparation or execution fails.
     */
    private function executeStatement(): PDOStatement
    {
        $sql = $this->buildSql();

        $stmt = DatabaseConnection::getConnection()->prepare($sql);
        if ($stmt === false) {
            $error = DatabaseConnection::getConnection()->errorInfo();
            throw new RuntimeException("Failed to prepare query: {$sql} - Error: {$error[2]}");
        }
        $stmt->execute($this->bindings);

        if ($stmt->errorCode() !== '00000') {
            $error = $stmt->errorInfo();
            throw new RuntimeException("Query failed: {$sql} - Error: {$error[2]}");
        }

        return $stmt;
    }

    /**
     * Builds the complete SQL query from the configured clauses.
     *
     * @return string The complete SQL query string.
     * @throws RuntimeException If the table name is not set.
     */
    private function buildSql(): string
    {
        if (empty($this->table)) {
            throw new RuntimeException('Table name must be set');
        }

        $clauses = [
            $this->buildOperationClause(),
            implode(' ', array_filter($this->joins)),
            $this->buildWhereClause(),
            $this->buildGroupByClause(),
            $this->buildHavingClause(),
            $this->buildOrderByClause(),
            $this->buildLimitClause(),
            $this->buildOffsetClause(),
        ];

        return implode(' ', array_filter($clauses));
    }

    /**
     * Builds the operation clause (SELECT, UPDATE, DELETE) for the query.
     *
     * @return string The operation clause string.
     */
    private function buildOperationClause(): string
    {
        if ($this->operation === 'SELECT') {
            $fields = empty($this->select) ? '*' : implode(', ', $this->select);
            return sprintf('SELECT %s FROM %s', $fields, $this->table);
        }

        return sprintf('%s FROM %s', $this->operation, $this->table);
    }

    /**
     * Builds the WHERE clause for the query.
     *
     * @return string The WHERE clause string, or empty if no conditions exist.
     */
    private function buildWhereClause(): string
    {
        if (empty($this->where)) {
            return '';
        }
        return 'WHERE ' . implode(' ', $this->where);
    }

    /**
     * Builds the GROUP BY clause for the query.
     *
     * @return string The GROUP BY clause string, or empty if no columns are specified.
     */
    private function buildGroupByClause(): string
    {
        return empty($this->groupBy) ? '' : 'GROUP BY ' . implode(', ', $this->groupBy);
    }

    /**
     * Builds the HAVING clause for the query.
     *
     * @return string The HAVING clause string, or empty if no conditions exist.
     */
    private function buildHavingClause(): string
    {
        return empty($this->having) ? '' : 'HAVING ' . implode(' AND ', $this->having);
    }

    /**
     * Builds the ORDER BY clause for the query.
     *
     * @return string The ORDER BY clause string, or empty if no ordering is specified.
     */
    private function buildOrderByClause(): string
    {
        return empty($this->orderBy) ? '' : 'ORDER BY ' . implode(', ', $this->orderBy);
    }

    /**
     * Builds the LIMIT clause for the query.
     *
     * @return string The LIMIT clause string, or empty if no limit is set.
     */
    private function buildLimitClause(): string
    {
        return $this->limit >= 0 ? "LIMIT {$this->limit}" : '';
    }

    /**
     * Builds the OFFSET clause for the query.
     *
     * @return string The OFFSET clause string, or empty if no offset is set.
     */
    private function buildOffsetClause(): string
    {
        return $this->offset >= 0 ? "OFFSET {$this->offset}" : '';
    }

    /**
     * Adds a condition to the WHERE clause with the specified conjunction.
     *
     * @param string $key The column name or expression to filter on.
     * @param mixed $value The value to compare against.
     * @param string $op The comparison operator.
     * @param string $conjunction The conjunction to use ('AND' or 'OR').
     * @return void
     */
    private function addCondition(string $key, mixed $value, string $op, string $conjunction): void
    {
        // Sanitize key for placeholder by removing dots and replacing with underscores
        $placeholderKey = str_replace('.', '_', $key) . '_' . count($this->bindings);
        $placeholder = ':' . $placeholderKey;
        $condition = sprintf('%s %s %s', $key, $op, $placeholder);
        $this->where[] = empty($this->where) ? $condition : "$conjunction $condition";
        $this->bindings[$placeholder] = $value;
    }

    /**
     * Hydrates an array of query results into model instances or objects.
     *
     * @param array $results The query results as associative arrays.
     * @return array The hydrated results as model instances or objects.
     */
    private function hydrateModels(array $results): array
    {
        if (!$this->model) {
            return $results;
        }
        return array_map([$this, 'hydrateModel'], $results);
    }

    /**
     * Hydrates a single query result into a model instance or object.
     *
     * @param array $data The query result as an associative array.
     * @return object The hydrated model instance or object.
     */
    private function hydrateModel(array $data): object
    {
        if (!$this->model) {
            return (object)$data;
        }

        $model = new $this->model();
        foreach ($data as $key => $value) {
            $model->__set($key, $value);
        }
        return $model;
    }
}
