<?php

namespace AdaiasMagdiel\Rubik;

use AdaiasMagdiel\Rubik\Relation\BelongsTo;
use AdaiasMagdiel\Rubik\Relation\HasMany;
use AdaiasMagdiel\Rubik\Relation\HasOne;
use BadMethodCallException;
use Generator;
use PDO;
use PDOStatement;
use RuntimeException;
use InvalidArgumentException;

/**
 * Query Builder for Rubik ORM.
 * 
 * Provides a fluent interface for constructing and executing SQL queries.
 * Supports SELECT, INSERT, UPDATE, DELETE operations with conditions, joins,
 * pagination, and automatic model hydration.
 * 
 * Security Features:
 * - All user inputs are sanitized to prevent SQL injection.
 * - Prepared statements with parameter binding.
 * - Strict validation of operators and identifiers.
 * 
 * @package AdaiasMagdiel\Rubik
 * @author  Adaías Magdiel
 */
class Query
{
    // ============================================================================
    // PROPERTIES
    // ============================================================================

    /**
     * Fully qualified model class name.
     * 
     * @var string
     */
    private string $model = '';

    /**
     * Target table name (sanitized).
     * 
     * @var string
     */
    private string $table = '';

    /**
     * Current operation type (SELECT, INSERT, UPDATE, DELETE).
     * 
     * @var string
     */
    private string $operation = 'SELECT';

    /**
     * SELECT clause fields.
     * 
     * @var array<int, string>
     */
    private array $select = [];

    /**
     * WHERE clause conditions.
     * 
     * @var array<int, string>
     */
    private array $where = [];

    /**
     * PDO parameter bindings.
     * 
     * @var array<string, mixed>
     */
    private array $bindings = [];

    /**
     * ORDER BY clause.
     * 
     * @var array<int, string>
     */
    private array $orderBy = [];

    /**
     * GROUP BY clause.
     * 
     * @var array<int, string>
     */
    private array $groupBy = [];

    /**
     * HAVING clause conditions.
     * 
     * @var array<int, string>
     */
    private array $having = [];

    /**
     * JOIN clauses.
     * 
     * @var array<int, string>
     */
    private array $joins = [];

    /**
     * LIMIT value (-1 means no limit).
     * 
     * @var int
     */
    private int $limit = -1;

    /**
     * OFFSET value (-1 means no offset).
     * 
     * @var int
     */
    private int $offset = -1;

    /**
     * Query execution log for debugging.
     * 
     * @var array<int, array{sql: string, bindings: array<string, mixed>, time: float}>
     */
    private array $queryLog = [];

    /**
     * List of relationships to be eager loaded.
     * 
     * @var array<int, string>
     */
    private array $eagerLoad = [];

    // ============================================================================
    // CONFIGURATION METHODS
    // ============================================================================

    /**
     * Sets the target table name.
     * 
     * @param string $table Table name (will be sanitized).
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If table name contains invalid characters.
     */
    public function setTable(string $table): self
    {
        $this->table = $this->sanitizeIdentifier($table);
        return $this;
    }

    /**
     * Sets the model class and automatically resolves the table name.
     * 
     * @param string $model Fully qualified model class name.
     * 
     * @return self
     * 
     * @throws RuntimeException If the model class does not exist.
     * @throws InvalidArgumentException If the resolved table name is invalid.
     */
    public function setModel(string $model): self
    {
        if (!class_exists($model)) {
            throw new RuntimeException("Model class {$model} does not exist");
        }

        $this->model = $model;
        $this->table = $this->sanitizeIdentifier($model::getTableName());

        return $this;
    }

    /**
     * Sets relationships to be eager loaded.
     *
     * @param string|array<int, string> $relations Relationship names to load.
     * @return self
     */
    public function with(string|array $relations): self
    {
        $this->eagerLoad = array_merge(
            $this->eagerLoad,
            is_string($relations) ? func_get_args() : $relations
        );
        return $this;
    }

    // ============================================================================
    // SELECT CLAUSE METHODS
    // ============================================================================

    /**
     * Specifies which columns to select.
     * 
     * Supports:
     * - Single column: select('id')
     * - Multiple columns: select(['id', 'name', 'email'])
     * - Column aliases: select(['id', 'name AS user_name'])
     * - SQL expressions: select([SQL::raw('COUNT(*) AS total')])
     * - All columns: select('*') or select()
     * 
     * When using a model, the primary key is automatically included unless
     * the query consists purely of raw SQL expressions (aggregates).
     * 
     * @param string|array<int, string|SQL> $fields Column names, SQL instances, or '*' for all.
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If column name or alias format is invalid.
     */
    public function select(string|array $fields = '*'): self
    {
        $fields = is_string($fields) ? [$fields] : $fields;

        // Handle wildcard selection
        if ($fields === ['*']) {
            $this->select = ['*'];
            return $this;
        }

        // Sanitize and validate all fields
        $sanitizedFields = [];
        // Track if we are selecting standard columns (to decide on PK injection)
        $hasStandardColumn = false;

        foreach ($fields as $field) {
            // Case 1: Explicit Raw SQL Instance
            if ($field instanceof SQL) {
                $sanitizedFields[] = (string) $field;
                continue;
            }

            if (!is_string($field)) {
                throw new InvalidArgumentException('Fields must be strings or SQL instances.');
            }

            $trimmedField = trim($field);

            // Case 2: Handle Aliases (e.g., "column AS alias")
            // Note: We only support "col AS alias". "COUNT(*) AS alias" must use SQL::raw()
            if (preg_match('/^(.*?)\s+AS\s+(.*?)$/i', $trimmedField, $matches)) {
                $column = $matches[1];
                $alias = $matches[2];

                $safeColumn = Rubik::quoteIdentifier($column);
                $safeAlias = Rubik::quoteIdentifier($alias);

                $sanitizedFields[] = "$safeColumn AS $safeAlias";
                $hasStandardColumn = true;
            }
            // Case 3: Standard Column
            else {
                $sanitizedFields[] = Rubik::quoteIdentifier($trimmedField);
                $hasStandardColumn = true;
            }
        }

        // Logic to ensure Primary Key is selected if we are querying a Model
        // and we are selecting actual columns (not just aggregates via SQL::raw)
        if ($this->model && $hasStandardColumn) {
            $pk = $this->model::primaryKey();
            $quotedPk = Rubik::quoteIdentifier($pk);
            $quotedQualifiedPk = Rubik::quoteIdentifier($this->table) . '.' . $quotedPk;

            // Check if the PK is already present in the sanitized list
            $pkIsSelected = false;
            foreach ($sanitizedFields as $sf) {
                // Strip alias for comparison: "`col` AS `alias`" -> "`col`"
                $colPart = preg_split('/\s+AS\s+/i', $sf)[0];
                if ($colPart === $quotedPk || $colPart === $quotedQualifiedPk || $colPart === '*') {
                    $pkIsSelected = true;
                    break;
                }
            }

            if (!$pkIsSelected) {
                array_unshift($sanitizedFields, $quotedQualifiedPk);
            }
        }

        $this->select = array_unique($sanitizedFields);
        return $this;
    }

    /**
     * Eager load the specified relationships for a collection of models.
     *
     * This method preloads related models in bulk to avoid the N+1 query problem.
     * It dynamically resolves each relationship definition, executes the
     * appropriate queries, and hydrates the related results back into
     * the given models.
     *
     * Supported relationship types:
     * - BelongsTo
     * - HasOne
     * - HasMany
     *
     * Resolution strategy:
     * - BelongsTo:
     *   Collects all foreign key values from the parent models, queries the
     *   related model using the owner key, and maps the results back by key.
     *
     * - HasOne / HasMany:
     *   Collects all local key values from the parent models, queries the
     *   related model using the foreign key, and groups the results by key.
     *
     * Once resolved, each relationship result is cached on the model via
     * setRelation(), preventing additional queries on subsequent access.
     *
     * @param array<int, Model> $models
     *        The collection of parent models to eager load relations for.
     *
     * @return void
     */
    private function eagerLoadRelations(array $models): void
    {
        $instance = new $this->model();

        foreach ($this->eagerLoad as $relationName) {
            if (!method_exists($instance, $relationName)) {
                continue;
            }

            $relation = $instance->$relationName();

            if ($relation instanceof BelongsTo) {
                $foreignKey = $relation->getForeignKey();
                $ownerKey = $relation->getOwnerKey();

                $ids = [];
                foreach ($models as $model) {
                    if (isset($model->$foreignKey)) {
                        $ids[] = $model->$foreignKey;
                    }
                }
                $ids = array_unique($ids);
                if (empty($ids)) continue;


                $relatedQuery = $relation->getQuery();
                $relatedModels = $relatedQuery->whereIn($ownerKey, $ids)->all();


                $dictionary = [];
                foreach ($relatedModels as $rel) {
                    $dictionary[$rel->$ownerKey] = $rel;
                }


                foreach ($models as $model) {
                    $fkValue = $model->$foreignKey ?? null;
                    if ($fkValue && isset($dictionary[$fkValue])) {
                        $model->setRelation($relationName, $dictionary[$fkValue]);
                    }
                }
            } elseif ($relation instanceof HasMany || $relation instanceof HasOne) {

                $localKey = $relation->getLocalKey();
                $foreignKey = $relation->getForeignKey();

                $ids = [];
                foreach ($models as $model) {
                    if (isset($model->$localKey)) {
                        $ids[] = $model->$localKey;
                    }
                }
                $ids = array_unique($ids);
                if (empty($ids)) continue;

                $relatedQuery = $relation->getQuery();
                $relatedModels = $relatedQuery->whereIn($foreignKey, $ids)->all();

                $dictionary = [];
                foreach ($relatedModels as $rel) {
                    $fkVal = $rel->$foreignKey;
                    $dictionary[$fkVal][] = $rel;
                }

                foreach ($models as $model) {
                    $pkValue = $model->$localKey ?? null;
                    if ($pkValue && isset($dictionary[$pkValue])) {
                        $value = ($relation instanceof \AdaiasMagdiel\Rubik\Relation\HasOne)
                            ? $dictionary[$pkValue][0]
                            : $dictionary[$pkValue];
                        $model->setRelation($relationName, $value);
                    }
                }
            }
        }
    }

    // ============================================================================
    // WHERE CLAUSE METHODS
    // ============================================================================

    /**
     * Adds a WHERE condition with AND conjunction.
     * 
     * Supports two call signatures:
     * - where('column', 'value')          → column = value
     * - where('column', 'operator', value) → column operator value
     * 
     * @param string $key Column name (e.g., 'id' or 'users.id').
     * @param mixed $operatorOrValue Operator or value if operator is omitted.
     * @param mixed $value Value (optional if operator is omitted).
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If column name or operator is invalid.
     * 
     * @example
     * ```php
     * $query->where('age', 18);              // age = 18
     * $query->where('age', '>', 18);         // age > 18
     * $query->where('status', 'IS', null);   // status IS NULL
     * ```
     */
    public function where(string $key, $operatorOrValue, $value = null): self
    {
        // Handle 3-argument form: where('col', 'operator', value)
        if ($value !== null) {
            $this->addCondition($key, $value, $operatorOrValue, 'AND');
            return $this;
        }

        // Handle 2-argument form: where('col', value)
        // But check if operatorOrValue is actually an operator keyword with null
        if (
            is_string($operatorOrValue) &&
            in_array(strtoupper($operatorOrValue), ['IS', 'IS NOT'], true)
        ) {
            // This is actually: where('col', 'IS') - value is implicitly null
            $this->addCondition($key, null, $operatorOrValue, 'AND');
            return $this;
        }

        // Standard 2-arg: where('col', value) → col = value
        $this->addCondition($key, $operatorOrValue, '=', 'AND');
        return $this;
    }

    /**
     * Adds a WHERE condition with OR conjunction.
     * 
     * @param string $key Column name.
     * @param mixed $operatorOrValue Operator or value.
     * @param mixed $value Value (optional).
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If column name or operator is invalid.
     * 
     * @example
     * ```php
     * $query->where('role', 'admin')
     *       ->orWhere('role', 'editor');
     * // WHERE role = 'admin' OR role = 'editor'
     * ```
     */
    public function orWhere(string $key, $operatorOrValue, $value = null): self
    {
        // Handle 3-argument form: orWhere('col', 'operator', value)
        if ($value !== null) {
            $this->addCondition($key, $value, $operatorOrValue, 'OR');
            return $this;
        }

        // Handle special case: orWhere('col', 'IS') for IS NULL
        if (
            is_string($operatorOrValue) &&
            in_array(strtoupper($operatorOrValue), ['IS', 'IS NOT'], true)
        ) {
            $this->addCondition($key, null, $operatorOrValue, 'OR');
            return $this;
        }

        // Standard 2-arg: orWhere('col', value) → col = value
        $this->addCondition($key, $operatorOrValue, '=', 'OR');
        return $this;
    }

    /**
     * Adds a WHERE IN condition.
     * 
     * @param string $key Column name.
     * @param array<int, mixed> $values Array of values to match.
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If values array is empty or column is invalid.
     * 
     * @example
     * ```php
     * $query->whereIn('status', ['active', 'pending', 'approved']);
     * // WHERE status IN ('active', 'pending', 'approved')
     * ```
     */
    public function whereIn(string $key, array $values): self
    {
        if (empty($values)) {
            throw new InvalidArgumentException('whereIn requires a non-empty array');
        }

        $key = $this->sanitizeColumnReference($key);
        $this->addCondition($key, $values, 'IN', 'AND');
        return $this;
    }

    /**
     * Adds a WHERE EXISTS subquery condition.
     * 
     * @param Query $subquery The subquery to check existence.
     * 
     * @return self
     * 
     * @example
     * ```php
     * $subquery = (new Query())->setTable('orders')
     *     ->select('1')
     *     ->where('orders.user_id', '=', 'users.id');
     * 
     * $query->setTable('users')->whereExists($subquery);
     * // WHERE EXISTS (SELECT 1 FROM orders WHERE orders.user_id = users.id)
     * ```
     */
    public function whereExists(Query $subquery): self
    {
        $sql = $subquery->getSql();
        $condition = "EXISTS ($sql)";

        if (empty($this->where)) {
            $this->where[] = $condition;
        } else {
            $this->where[] = "AND $condition";
        }

        $this->bindings = array_merge($this->bindings, $subquery->getBindings());
        return $this;
    }

    // ============================================================================
    // JOIN METHODS
    // ============================================================================

    /**
     * Adds an INNER JOIN clause.
     * 
     * @param string $table Table name to join.
     * @param string $left Left side of the join condition (usually qualified: table.column).
     * @param string $op Join operator (=, <>, <, >, <=, >=).
     * @param string $right Right side of the join condition.
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If table/column names or operator are invalid.
     * 
     * @example
     * ```php
     * $query->join('orders', 'users.id', '=', 'orders.user_id');
     * // INNER JOIN orders ON users.id = orders.user_id
     * ```
     */
    public function join(string $table, string $left, string $op, string $right): self
    {
        $table = $this->sanitizeIdentifier($table);
        $left = $this->sanitizeColumnReference($left);
        $right = $this->sanitizeColumnReference($right);

        if (!in_array($op, ['=', '<>', '<', '>', '<=', '>='], true)) {
            throw new InvalidArgumentException("Invalid join operator: $op");
        }

        $this->joins[] = "INNER JOIN $table ON $left $op $right";
        return $this;
    }

    /**
     * Adds a LEFT JOIN clause.
     * 
     * @param string $table Table name to join.
     * @param string $left Left side of the join condition.
     * @param string $op Join operator.
     * @param string $right Right side of the join condition.
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If table/column names or operator are invalid.
     */
    public function leftJoin(string $table, string $left, string $op, string $right): self
    {
        $table = $this->sanitizeIdentifier($table);
        $left = $this->sanitizeColumnReference($left);
        $right = $this->sanitizeColumnReference($right);

        if (!in_array($op, ['=', '<>', '<', '>', '<=', '>='], true)) {
            throw new InvalidArgumentException("Invalid join operator: $op");
        }

        $this->joins[] = "LEFT JOIN $table ON $left $op $right";
        return $this;
    }

    /**
     * Adds a RIGHT JOIN clause.
     * 
     * @param string $table Table name to join.
     * @param string $left Left side of the join condition.
     * @param string $op Join operator.
     * @param string $right Right side of the join condition.
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If table/column names or operator are invalid.
     */
    public function rightJoin(string $table, string $left, string $op, string $right): self
    {
        $table = $this->sanitizeIdentifier($table);
        $left = $this->sanitizeColumnReference($left);
        $right = $this->sanitizeColumnReference($right);

        if (!in_array($op, ['=', '<>', '<', '>', '<=', '>='], true)) {
            throw new InvalidArgumentException("Invalid join operator: $op");
        }

        $this->joins[] = "RIGHT JOIN $table ON $left $op $right";
        return $this;
    }

    // ============================================================================
    // ORDERING AND GROUPING METHODS
    // ============================================================================

    /**
     * Adds an ORDER BY clause.
     * 
     * @param string $column Column name to order by.
     * @param string $direction Sort direction: 'ASC' or 'DESC' (default: 'ASC').
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If column name is invalid.
     * 
     * @example
     * ```php
     * $query->orderBy('created_at', 'DESC')->orderBy('name', 'ASC');
     * // ORDER BY created_at DESC, name ASC
     * ```
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $column = $this->sanitizeColumnReference($column);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    /**
     * Adds a GROUP BY clause.
     * 
     * @param string $column Column name to group by.
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If column name is invalid.
     * 
     * @example
     * ```php
     * $query->select(['status', 'COUNT(*) AS total'])
     *       ->groupBy('status');
     * // SELECT status, COUNT(*) AS total FROM users GROUP BY status
     * ```
     */
    public function groupBy(string $column): self
    {
        $column = $this->sanitizeColumnReference($column);
        $this->groupBy[] = $column;
        return $this;
    }

    /**
     * Adds a HAVING clause.
     * 
     * Note: HAVING conditions are validated for dangerous characters but
     * not fully sanitized due to their complex nature. Use with caution.
     * 
     * @param string $condition The HAVING condition.
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If condition contains dangerous characters.
     * 
     * @example
     * ```php
     * $query->select(['status', 'COUNT(*) AS total'])
     *       ->groupBy('status')
     *       ->having('COUNT(*) > 10');
     * ```
     */
    public function having(string $condition): self
    {
        // Basic validation - reject obvious SQL injection attempts
        if (preg_match('/[;\'"\\\\]/', $condition)) {
            throw new InvalidArgumentException("Invalid characters in HAVING clause");
        }

        $this->having[] = $condition;
        return $this;
    }

    // ============================================================================
    // LIMIT AND OFFSET METHODS
    // ============================================================================

    /**
     * Sets the LIMIT clause.
     * 
     * @param int $limit Maximum number of rows to return.
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If limit is negative.
     * 
     * @example
     * ```php
     * $query->limit(10); // LIMIT 10
     * ```
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
     * Sets the OFFSET clause.
     * 
     * @param int $offset Number of rows to skip.
     * 
     * @return self
     * 
     * @throws InvalidArgumentException If offset is negative.
     * 
     * @example
     * ```php
     * $query->limit(10)->offset(20); // Skip 20 rows, return next 10
     * ```
     */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be non-negative');
        }

        $this->offset = $offset;
        return $this;
    }

    // ============================================================================
    // EXECUTION METHODS - WRITE OPERATIONS
    // ============================================================================

    /**
     * Executes a DELETE query.
     * 
     * WARNING: Without WHERE conditions, this will delete ALL rows!
     * 
     * @return bool True if at least one row was deleted.
     * 
     * @throws RuntimeException If query preparation or execution fails.
     * 
     * @example
     * ```php
     * $query->setTable('users')
     *       ->where('status', 'inactive')
     *       ->delete();
     * ```
     */
    public function delete(): bool
    {
        $this->operation = 'DELETE';

        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->table,
            $this->buildWhereClause() ? ' ' . $this->buildWhereClause() : ''
        );

        $stmt = Rubik::getConn()->prepare($sql);
        if ($stmt === false) {
            $error = Rubik::getConn()->errorInfo();
            throw new RuntimeException("Failed to prepare delete: {$sql} - Error: {$error[2]}");
        }

        return $stmt->execute($this->bindings);
    }

    /**
     * Executes an UPDATE query.
     * 
     * WARNING: Without WHERE conditions, this will update ALL rows!
     * 
     * @param array<string, mixed> $data Associative array of column => value pairs.
     * 
     * @return bool True if at least one row was updated, false if data is empty.
     * 
     * @throws InvalidArgumentException If data contains invalid column names.
     * @throws RuntimeException If query preparation or execution fails.
     * 
     * @example
     * ```php
     * $query->setTable('users')
     *       ->where('id', 1)
     *       ->update(['name' => 'John', 'status' => 'active']);
     * ```
     */
    public function update(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $sets = [];
        foreach ($data as $key => $value) {
            $key = $this->sanitizeIdentifier($key);

            // Handle SQL::raw() expressions
            if ($value instanceof SQL) {
                $sets[] = "$key = $value";
                continue;
            }

            $placeholder = ":set_{$key}_" . count($this->bindings);
            $sets[] = "$key = $placeholder";
            $this->bindings[$placeholder] = $value;
        }

        $this->operation = 'UPDATE';
        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->table,
            implode(', ', $sets),
            $this->buildWhereClause() ? ' ' . $this->buildWhereClause() : ''
        );

        $stmt = Rubik::getConn()->prepare($sql);
        if ($stmt === false) {
            $error = Rubik::getConn()->errorInfo();
            throw new RuntimeException("Failed to prepare query: {$sql} - Error: {$error[2]}");
        }

        return $stmt->execute($this->bindings);
    }

    /**
     * Executes an INSERT query.
     * 
     * Supports both single and batch inserts.
     * 
     * @param array<string, mixed>|array<int, array<string, mixed>> $data
     *        Single row: ['column' => 'value', ...]
     *        Multiple rows: [['column' => 'value'], ['column' => 'value']]
     * 
     * @return array<int, mixed> The list of inserted primary keys (if available/supported by driver).
     * 
     * @throws InvalidArgumentException If data is empty or contains invalid column names.
     * @throws RuntimeException If query preparation or execution fails.
     * 
     * @example
     * ```php
     * // Single insert
     * $ids = $query->insert(['name' => 'John', 'email' => 'john@example.com']);
     * 
     * // Batch insert
     * $ids = $query->insert([
     *     ['name' => 'John', 'email' => 'john@example.com'],
     *     ['name' => 'Jane', 'email' => 'jane@example.com']
     * ]);
     * ```
     */
    public function insert(array $data)
    {
        if (empty($data)) {
            throw new InvalidArgumentException("Insert data cannot be empty");
        }

        // Normalize to array of rows
        $rows = isset($data[0]) && is_array($data[0]) ? $data : [$data];
        $columns = array_keys($rows[0]);

        // Sanitize column names
        $sanitizedColumns = array_map([$this, 'sanitizeIdentifier'], $columns);
        $colList = implode(', ', $sanitizedColumns);

        $placeholdersRows = [];
        $this->operation = 'INSERT';

        // Build placeholders for each row
        foreach ($rows as $rowIndex => $row) {
            $placeholders = [];

            foreach ($columns as $col) {
                $ph = ":{$col}_{$rowIndex}";
                $placeholders[] = $ph;
                $this->bindings[$ph] = $row[$col];
            }

            $placeholdersRows[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            $this->table,
            $colList,
            implode(', ', $placeholdersRows)
        );

        $stmt = Rubik::getConn()->prepare($sql);
        if (!$stmt) {
            $error = Rubik::getConn()->errorInfo();
            throw new RuntimeException("Failed to prepare INSERT: {$sql} - {$error[2]}");
        }

        $stmt->execute($this->bindings);

        return $this->resolveInsertedIds($stmt, $rows);
    }

    // ============================================================================
    // EXECUTION METHODS - READ OPERATIONS
    // ============================================================================

    /**
     * Executes query and returns paginated results.
     * 
     * @param int $page Page number (1-indexed).
     * @param int $perPage Number of items per page.
     * 
     * @return array{
     *     data: array<int, object>,
     *     total: int,
     *     per_page: int,
     *     current_page: int,
     *     last_page: int
     * }
     * 
     * @throws InvalidArgumentException If page or perPage is less than 1.
     * 
     * @example
     * ```php
     * $result = $query->paginate(page: 2, perPage: 20);
     * echo "Showing {$result['current_page']} of {$result['last_page']} pages";
     * foreach ($result['data'] as $item) { ... }
     * ```
     */
    public function paginate(int $page, int $perPage): array
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be at least 1');
        }
        if ($perPage < 1) {
            throw new InvalidArgumentException('PerPage must be at least 1');
        }

        $total = $this->count();
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $data = $this->all();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Counts total rows matching the query (ignoring LIMIT/OFFSET).
     * 
     * This method temporarily modifies the query state but restores it
     * even if an exception occurs.
     * 
     * @return int Total number of matching rows.
     * 
     * @throws RuntimeException If query execution fails.
     * 
     * @example
     * ```php
     * $total = $query->where('status', 'active')->count();
     * echo "Found $total active users";
     * ```
     */
    public function count(): int
    {
        $originalSelect = $this->select;
        $originalLimit = $this->limit;
        $originalOffset = $this->offset;

        try {
            $this->select = ['COUNT(*) AS count'];
            $this->limit = -1;
            $this->offset = -1;

            $stmt = $this->executeStatement();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (int) $result['count'] : 0;
        } finally {
            // Always restore original state
            $this->select = $originalSelect;
            $this->limit = $originalLimit;
            $this->offset = $originalOffset;
        }
    }

    /**
     * Executes query and returns all matching rows.
     * 
     * WARNING: For large result sets, consider using cursor() or chunk()
     * to avoid memory issues.
     * 
     * @return array<int, object> Array of model instances or stdClass objects.
     * 
     * @throws RuntimeException If query execution fails.
     * 
     * @example
     * ```php
     * $users = $query->where('status', 'active')->all();
     * foreach ($users as $user) {
     *     echo $user->name;
     * }
     * ```
     */
    public function all(): array
    {
        $stmt = $this->executeStatement();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->hydrateModels($results);
    }

    /**
     * Executes query and returns the first matching row.
     * 
     * Automatically adds LIMIT 1 to the query.
     * 
     * @return object|null Model instance/stdClass or null if no rows found.
     * 
     * @throws RuntimeException If query execution fails.
     * 
     * @example
     * ```php
     * $user = $query->where('email', 'john@example.com')->first();
     * if ($user) {
     *     echo $user->name;
     * }
     * ```
     */
    public function first(): ?object
    {
        $this->limit(1);
        $stmt = $this->executeStatement();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $this->hydrateModel($result) : null;
    }

    /**
     * Executes query without fetching results.
     * 
     * Useful for checking if any rows match without loading data.
     * 
     * @return bool True if at least one row matches.
     * 
     * @throws RuntimeException If query execution fails.
     * 
     * @example
     * ```php
     * $exists = $query->where('email', 'john@example.com')->exec();
     * if ($exists) {
     *     echo "Email already registered";
     * }
     * ```
     */
    public function exec(): bool
    {
        // Save original limit
        $originalLimit = $this->limit;

        try {
            // Optimize: only fetch 1 row to check existence
            $this->limit(1);
            $stmt = $this->executeStatement();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result !== false;
        } finally {
            // Restore original limit
            $this->limit = $originalLimit;
        }
    }

    /**
     * Iterates over results using a memory-efficient generator.
     * 
     * This method uses lazy loading to fetch one row at a time,
     * making it ideal for processing large datasets.
     * 
     * @return Generator<int, object> Generator yielding model instances.
     * 
     * @throws RuntimeException If query execution fails.
     * 
     * @example
     * ```php
     * foreach ($query->cursor() as $user) {
     *     // Process one user at a time
     *     processUser($user);
     * }
     * ```
     */
    public function cursor(): Generator
    {
        $stmt = $this->executeStatement();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $this->hydrateModel($row);
        }
    }

    /**
     * Processes results in chunks to manage memory usage.
     * 
     * Executes the callback for each chunk of results. Useful for
     * processing large datasets without loading everything into memory.
     * 
     * @param int $size Number of rows per chunk.
     * @param callable $callback Function to call for each chunk.
     *                           Receives array of models as parameter.
     * 
     * @return void
     * 
     * @throws InvalidArgumentException If chunk size is less than 1.
     * 
     * @example
     * ```php
     * $query->chunk(100, function($users) {
     *     foreach ($users as $user) {
     *         sendEmail($user);
     *     }
     * });
     * ```
     */
    public function chunk(int $size, callable $callback): void
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1');
        }

        $page = 1;
        do {
            $results = $this->paginate($page++, $size)['data'];
            if (empty($results)) {
                break;
            }
            $callback($results);
        } while (count($results) === $size);
    }

    // ============================================================================
    // QUERY INTROSPECTION METHODS
    // ============================================================================

    /**
     * Gets the generated SQL query string.
     * 
     * Note: Placeholders are not interpolated. Use getBindings() to see values.
     * 
     * @return string The complete SQL query with placeholders.
     * 
     * @throws RuntimeException If table is not set or operation is UPDATE.
     * 
     * @example
     * ```php
     * echo $query->where('id', 1)->getSql();
     * // SELECT * FROM users WHERE id = :id_0
     * ```
     */
    public function getSql(): string
    {
        if (empty($this->table)) {
            throw new RuntimeException('Table name must be set');
        }

        $selectFields = empty($this->select) || $this->select === ['*']
            ? '*'
            : implode(', ', $this->select);

        if ($this->operation === 'UPDATE') {
            throw new RuntimeException('Cannot get SQL for UPDATE without calling update()');
        }

        if ($this->operation === 'DELETE') {
            return sprintf(
                'DELETE FROM %s%s',
                $this->table,
                $this->buildWhereClause() ? ' ' . $this->buildWhereClause() : ''
            );
        }

        $sqlParts = array_filter([
            sprintf('SELECT %s FROM %s', $selectFields, $this->table),
            $this->buildJoinsClause(),
            $this->buildWhereClause(),
            $this->buildGroupByClause(),
            $this->buildHavingClause(),
            $this->buildOrderByClause(),
            $this->buildLimitClause(),
            $this->buildOffsetClause(),
        ]);

        return implode(' ', $sqlParts);
    }

    /**
     * Gets all parameter bindings for the current query.
     * 
     * @return array<string, mixed> Associative array of placeholder => value.
     * 
     * @example
     * ```php
     * $query->where('id', 1)->where('status', 'active');
     * print_r($query->getBindings());
     * // [':id_0' => 1, ':status_1' => 'active']
     * ```
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Gets the query execution log.
     * 
     * Contains all queries executed with their SQL, bindings, and execution time.
     * Useful for debugging and performance monitoring.
     * 
     * @return array<int, array{sql: string, bindings: array<string, mixed>, time: float}>
     * 
     * @example
     * ```php
     * $query->where('id', 1)->first();
     * foreach ($query->getQueryLog() as $log) {
     *     echo "{$log['sql']} took {$log['time']}s\n";
     * }
     * ```
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    // ============================================================================
    // PRIVATE HELPER METHODS - SQL BUILDING
    // ============================================================================

    /**
     * Executes the query and returns the PDOStatement.
     * 
     * This is the central method that:
     * 1. Builds the SQL.
     * 2. Prepares the statement.
     * 3. Executes with bindings.
     * 4. Logs the query.
     * 5. Handles errors.
     * 
     * @return PDOStatement
     * 
     * @throws RuntimeException If preparation or execution fails.
     */
    private function executeStatement(): PDOStatement
    {
        if (empty($this->table)) {
            throw new RuntimeException('Table name must be set');
        }

        $sql = $this->getSql();
        $start = microtime(true);

        $stmt = Rubik::getConn()->prepare($sql);
        if ($stmt === false) {
            $error = Rubik::getConn()->errorInfo();
            throw new RuntimeException("Failed to prepare query: {$sql} - Error: {$error[2]}");
        }

        $stmt->execute($this->bindings);

        // Log query for debugging
        $this->queryLog[] = [
            'sql' => $sql,
            'bindings' => $this->bindings,
            'time' => microtime(true) - $start,
        ];

        if ($stmt->errorCode() !== '00000') {
            $error = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: {$sql} - Error: {$error[2]}");
        }

        return $stmt;
    }

    /**
     * Builds the JOIN clauses.
     * 
     * @return string JOIN clauses or empty string.
     */
    private function buildJoinsClause(): string
    {
        return empty($this->joins) ? '' : implode(' ', $this->joins);
    }

    /**
     * Builds the WHERE clause.
     * 
     * @return string WHERE clause or empty string.
     */
    private function buildWhereClause(): string
    {
        return empty($this->where) ? '' : 'WHERE ' . implode(' ', $this->where);
    }

    /**
     * Builds the GROUP BY clause.
     * 
     * @return string GROUP BY clause or empty string.
     */
    private function buildGroupByClause(): string
    {
        return empty($this->groupBy) ? '' : 'GROUP BY ' . implode(', ', $this->groupBy);
    }

    /**
     * Builds the HAVING clause.
     * 
     * @return string HAVING clause or empty string.
     */
    private function buildHavingClause(): string
    {
        return empty($this->having) ? '' : 'HAVING ' . implode(' AND ', $this->having);
    }

    /**
     * Builds the ORDER BY clause.
     * 
     * @return string ORDER BY clause or empty string.
     */
    private function buildOrderByClause(): string
    {
        return empty($this->orderBy) ? '' : 'ORDER BY ' . implode(', ', $this->orderBy);
    }

    /**
     * Builds the LIMIT clause.
     * 
     * @return string LIMIT clause or empty string.
     */
    private function buildLimitClause(): string
    {
        return $this->limit >= 0 ? "LIMIT {$this->limit}" : '';
    }

    /**
     * Builds the OFFSET clause.
     * 
     * @return string OFFSET clause or empty string.
     */
    private function buildOffsetClause(): string
    {
        return $this->offset >= 0 ? "OFFSET {$this->offset}" : '';
    }

    // ============================================================================
    // PRIVATE HELPER METHODS - CONDITION BUILDING
    // ============================================================================

    /**
     * Adds a condition to the WHERE clause.
     * 
     * This is the core method that handles all WHERE conditions:
     * - Standard comparisons (=, <, >, etc.)
     * - NULL checks (IS NULL, IS NOT NULL)
     * - IN clauses
     * - SQL::raw() expressions
     * 
     * @param string $key Column name (must be sanitized before calling).
     * @param mixed $value Value to compare.
     * @param string $op Comparison operator.
     * @param string $conjunction AND or OR.
     * 
     * @return void
     * 
     * @throws InvalidArgumentException If operator is invalid.
     */
    private function addCondition(string $key, mixed $value, string $op, string $conjunction): void
    {
        $key = $this->sanitizeColumnReference($key);
        $op = strtoupper($op);

        $validOps = ['=', '<>', '<', '>', '<=', '>=', 'LIKE', 'ILIKE', 'IS', 'IS NOT', 'IN'];

        if (!in_array($op, $validOps, true)) {
            throw new InvalidArgumentException("Invalid operator: {$op}");
        }

        // Determine conjunction prefix (empty for first condition)
        $prefix = empty($this->where) ? '' : "$conjunction ";

        // Handle IS NULL / IS NOT NULL
        if ($op === 'IS' || $op === 'IS NOT') {
            if (!is_null($value)) {
                throw new InvalidArgumentException("Operator {$op} requires NULL value");
            }
            $this->where[] = $prefix . sprintf('%s %s NULL', $key, $op);
            return;
        }

        // Handle IN (...)
        if ($op === 'IN') {
            if (!is_array($value) || empty($value)) {
                throw new InvalidArgumentException("IN operator requires a non-empty array");
            }

            $placeholders = [];
            $safeKey = str_replace('.', '_', $key);

            foreach ($value as $i => $val) {
                $ph = ":{$safeKey}_in_{$i}";
                $placeholders[] = $ph;
                $this->bindings[$ph] = $val;
            }

            $this->where[] = $prefix . sprintf('%s IN (%s)', $key, implode(', ', $placeholders));
            return;
        }

        // Handle SQL::raw() expressions
        if ($value instanceof SQL) {
            $this->where[] = $prefix . sprintf('%s %s %s', $key, $op, $value);
            return;
        }

        // Handle standard conditions with parameter binding
        $safeKey = str_replace('.', '_', $key);
        $placeholderKey = $safeKey . '_' . count($this->bindings);
        $placeholder = ':' . $placeholderKey;

        $this->where[] = $prefix . sprintf('%s %s %s', $key, $op, $placeholder);
        $this->bindings[$placeholder] = $value;
    }

    // ============================================================================
    // PRIVATE HELPER METHODS - INSERT ID RESOLUTION
    // ============================================================================

    /**
     * Resolves inserted IDs after an INSERT operation.
     * 
     * This method handles multiple scenarios:
     * 
     * 1. PostgreSQL / MySQL 8.0.22+ with RETURNING clause
     *    → Fetches IDs directly from statement.
     * 
     * 2. Manual IDs (UUIDs, ULIDs, etc.)
     *    → Returns IDs from the input data.
     * 
     * 3. AUTO_INCREMENT (MySQL, SQLite) - single insert
     *    → Uses PDO::lastInsertId().
     * 
     * 4. AUTO_INCREMENT - batch insert (MySQL < 8.0.22, SQLite)
     *    → Returns empty array (known limitation).
     * 
     * @param PDOStatement $stmt Executed INSERT statement.
     * @param array<int, array<string, mixed>> $rows Inserted rows.
     * 
     * @return array<int, mixed> Array of inserted IDs.
     */
    private function resolveInsertedIds(PDOStatement $stmt, array $rows): array
    {
        $pk = $this->model ? $this->model::primaryKey() : 'id';
        $driver = Rubik::getConn()->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Strategy 1: RETURNING clause (PostgreSQL, MySQL >= 8.0.22)
        $supportsReturning = in_array($driver, ['pgsql']) ||
            ($driver === 'mysql' && version_compare(
                Rubik::getConn()->getAttribute(PDO::ATTR_SERVER_VERSION),
                '8.0.22',
                '>='
            ));

        if ($supportsReturning) {
            $returned = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($returned) && isset($returned[0][$pk])) {
                return array_map(fn($row) => $row[$pk], $returned);
            }
        }

        // Strategy 2: Manual IDs (UUIDs, ULIDs, provided by user)
        $allHaveManualId = true;
        foreach ($rows as $r) {
            if (!array_key_exists($pk, $r)) {
                $allHaveManualId = false;
                break;
            }
        }

        if ($allHaveManualId) {
            return array_map(fn($r) => $r[$pk], $rows);
        }

        // Strategy 3: AUTO_INCREMENT (single insert only)
        $lastId = Rubik::getConn()->lastInsertId();

        if ($lastId && count($rows) === 1) {
            return [(int)$lastId];
        }

        // For batch inserts without RETURNING, we cannot reliably get IDs
        // This is a known limitation for SQLite and MySQL < 8.0.22
        return [];
    }

    // ============================================================================
    // PRIVATE HELPER METHODS - MODEL HYDRATION
    // ============================================================================

    /**
     * Hydrates multiple result rows into model instances.
     * 
     * @param array<int, array<string, mixed>> $results Raw database rows.
     * 
     * @return array<int, object> Array of hydrated models or stdClass objects.
     */
    private function hydrateModels(array $results): array
    {
        if (!$this->model) {
            return $results;
        }

        $models = array_map([$this, 'hydrateModel'], $results);

        if (!empty($this->eagerLoad) && !empty($models)) {
            $this->eagerLoadRelations($models);
        }

        return $models;
    }

    /**
     * Hydrates a single result row into a model instance.
     * 
     * If a model class is set, creates an instance, populates its properties,
     * and sets the 'exists' flag to true.
     * Otherwise, returns a stdClass object.
     * 
     * @param array<string, mixed> $data Raw database row.
     * 
     * @return object Model instance or stdClass.
     */
    private function hydrateModel(array $data): object
    {
        if (!$this->model) {
            return (object)$data;
        }

        $model = new $this->model();

        if ($model instanceof Model) {
            $model->exists = true;
        }

        if (method_exists($model, 'hydrate')) {
            $model->hydrate($data);
        } else {
            // Fallback caso alguém sobrescreva algo errado (opcional)
            foreach ($data as $key => $value) {
                $model->{$key} = $value;
            }
            $model->exists = true;
        }

        return $model;
    }

    // ============================================================================
    // PRIVATE HELPER METHODS - INPUT SANITIZATION
    // ============================================================================

    /**
     * Quotes and sanitizes a simple identifier (table name, column name).
     * 
     * @param string $identifier Identifier to sanitize.
     * 
     * @return string Quoted identifier.
     * 
     * @throws InvalidArgumentException If identifier contains invalid characters.
     */
    private function sanitizeIdentifier(string $identifier): string
    {
        return Rubik::quoteIdentifier($identifier);
    }

    /**
     * Quotes and sanitizes a column reference.
     * 
     * Allows format: table.column or column.
     * 
     * @param string $column Column reference to sanitize.
     * 
     * @return string Quoted column reference.
     * 
     * @throws InvalidArgumentException If column reference contains invalid characters.
     */
    private function sanitizeColumnReference(string $column): string
    {
        // Allow qualified columns: table.column
        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            return implode('.', array_map([Rubik::class, 'quoteIdentifier'], $parts));
        }

        return Rubik::quoteIdentifier($column);
    }

    /**
     * Magic method to handle Query Scopes.
     * 
     * Allows calling model scopes defined as "scopeName($query)".
     * Example: User::scopeActive($query) is called via $query->active().
     * 
     * @param string $method The scope name being called.
     * @param array $args Arguments to pass to the scope.
     * 
     * @return self
     * 
     * @throws BadMethodCallException If the scope method does not exist on the model.
     */
    public function __call(string $method, array $args): self
    {
        $scope = 'scope' . ucfirst($method);

        if (method_exists($this->model, $scope)) {
            array_unshift($args, $this);
            call_user_func_array([$this->model, $scope], $args);

            return $this;
        }

        throw new BadMethodCallException(
            sprintf('Call to undefined method %s::%s()', static::class, $method)
        );
    }
}
