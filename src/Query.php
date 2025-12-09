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
    private string $model = '';
    private string $table = '';
    private string $operation = 'SELECT';
    private array $select = [];
    private array $where = [];
    private array $bindings = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private array $having = [];
    private array $joins = [];
    private int $limit = -1;
    private int $offset = -1;

    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function setModel(string $model): self
    {
        if (!class_exists($model)) {
            throw new RuntimeException("Model class {$model} does not exist");
        }
        $this->model = $model;
        $this->table = $model::getTableName();
        return $this;
    }

    public function select(string|array $fields = '*'): self
    {
        $fields = is_string($fields) ? [$fields] : $fields;
        if ($fields !== ['*']) {
            $pk = $this->model ? $this->model::primaryKey() : 'id';
            $qualifiedPk = $this->table . '.' . $pk;
            // Preserve aliases by not modifying fields with 'AS'
            $this->select = array_unique(array_merge(
                $this->select,
                [$qualifiedPk],
                array_filter($fields, fn($field) => !str_contains($field, ' AS '))
            ));
            // Add aliased fields separately to retain AS clauses
            foreach ($fields as $field) {
                if (str_contains($field, ' AS ')) {
                    $this->select[] = $field;
                }
            }
        } else {
            $this->select = ['*'];
        }
        return $this;
    }

    public function where(string $key, $operatorOrValue, $value = null): self
    {
        $op = $value === null ? '=' : $operatorOrValue;
        $val = $value === null ? $operatorOrValue : $value;
        $this->addCondition($key, $val, $op, 'AND');
        return $this;
    }

    public function orWhere(string $key, $operatorOrValue, $value = null): self
    {
        $op = $value === null ? '=' : $operatorOrValue;
        $val = $value === null ? $operatorOrValue : $value;
        $this->addCondition($key, $val, $op, 'OR');
        return $this;
    }

    public function whereIn(string $key, array $values): self
    {
        if (empty($values)) {
            throw new InvalidArgumentException('whereIn requires a non-empty array');
        }
        $placeholders = [];
        foreach ($values as $index => $value) {
            $placeholder = ":{$key}_in_{$index}";
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $value;
        }
        $this->where[] = empty($this->where)
            ? "$key IN (" . implode(', ', $placeholders) . ")"
            : "AND $key IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    public function join(string $table, string $left, string $op, string $right): self
    {
        $this->joins[] = "INNER JOIN $table ON $left $op $right";
        return $this;
    }

    public function leftJoin(string $table, string $left, string $op, string $right): self
    {
        $this->joins[] = "LEFT JOIN $table ON $left $op $right";
        return $this;
    }

    public function rightJoin(string $table, string $left, string $op, string $right): self
    {
        $this->joins[] = "RIGHT JOIN $table ON $left $op $right";
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    public function groupBy(string $column): self
    {
        $this->groupBy[] = $column;
        return $this;
    }

    public function having(string $condition): self
    {
        $this->having[] = $condition;
        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be non-negative');
        }
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be non-negative');
        }
        $this->offset = $offset;
        return $this;
    }

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


    public function update(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $sets = [];
        foreach ($data as $key => $value) {
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

    public function count(): int
    {
        $originalSelect = $this->select;
        $originalLimit = $this->limit;
        $originalOffset = $this->offset;
        $this->select = ['COUNT(*) AS count'];
        $this->limit = -1;
        $this->offset = -1;
        $stmt = $this->executeStatement();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->select = $originalSelect;
        $this->limit = $originalLimit;
        $this->offset = $originalOffset;
        return $result ? (int) $result['count'] : 0;
    }

    public function all(): array
    {
        $stmt = $this->executeStatement();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->hydrateModels($results);
    }

    public function first(): ?object
    {
        $this->limit(1);
        $stmt = $this->executeStatement();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $this->hydrateModel($result) : null;
    }

    public function exec(): bool
    {
        $stmt = $this->executeStatement();
        return $stmt->rowCount() > 0;
    }

    public function getSql(): string
    {
        if (empty($this->table)) {
            throw new RuntimeException('Table name must be set');
        }

        $selectFields = empty($this->select) || $this->select === ['*'] ? '*' : implode(', ', $this->select);

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

    private function executeStatement(): PDOStatement
    {
        if (empty($this->table)) {
            throw new RuntimeException('Table name must be set');
        }

        $sql = $this->getSql();
        $stmt = Rubik::getConn()->prepare($sql);
        if ($stmt === false) {
            $error = Rubik::getConn()->errorInfo();
            throw new RuntimeException("Failed to prepare query: {$sql} - Error: {$error[2]}");
        }
        $stmt->execute($this->bindings);

        if ($stmt->errorCode() !== '00000') {
            $error = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: {$sql} - Error: {$error[2]}");
        }

        return $stmt;
    }

    private function buildJoinsClause(): string
    {
        return empty($this->joins) ? '' : implode(' ', $this->joins);
    }

    private function buildWhereClause(): string
    {
        return empty($this->where) ? '' : 'WHERE ' . implode(' ', $this->where);
    }

    private function buildGroupByClause(): string
    {
        return empty($this->groupBy) ? '' : 'GROUP BY ' . implode(', ', $this->groupBy);
    }

    private function buildHavingClause(): string
    {
        return empty($this->having) ? '' : 'HAVING ' . implode(' AND ', $this->having);
    }

    private function buildOrderByClause(): string
    {
        return empty($this->orderBy) ? '' : 'ORDER BY ' . implode(', ', $this->orderBy);
    }

    private function buildLimitClause(): string
    {
        return $this->limit >= 0 ? "LIMIT {$this->limit}" : '';
    }

    private function buildOffsetClause(): string
    {
        return $this->offset >= 0 ? "OFFSET {$this->offset}" : '';
    }

    private function addCondition(string $key, mixed $value, string $op, string $conjunction): void
    {
        $op = strtoupper($op);

        $validOps = ['=', '<>', '<', '>', '<=', '>=', 'LIKE', 'ILIKE', 'IS', 'IS NOT', 'IN'];

        if (!in_array($op, $validOps, true)) {
            throw new InvalidArgumentException("Invalid operator: {$op}");
        }

        // NULL CHECKS
        if ($op === 'IS' || $op === 'IS NOT') {
            // value must be NULL
            if (!is_null($value)) {
                throw new InvalidArgumentException("Operator {$op} requires NULL value");
            }

            $condition = sprintf('%s %s NULL', $key, $op);
            $this->where[] = empty($this->where) ? $condition : "$conjunction $condition";
            return;
        }

        // IN (...)
        if ($op === 'IN') {
            if (!is_array($value) || empty($value)) {
                throw new InvalidArgumentException("IN operator requires a non-empty array");
            }

            $placeholders = [];
            foreach ($value as $i => $val) {
                $ph = ':' . str_replace('.', '_', $key) . "_in_{$i}";
                $placeholders[] = $ph;
                $this->bindings[$ph] = $val;
            }

            $condition = sprintf('%s IN (%s)', $key, implode(', ', $placeholders));
            $this->where[] = empty($this->where) ? $condition : "$conjunction $condition";
            return;
        }

        // SQL RAW VALUES
        if ($value instanceof SQL) {
            $condition = sprintf('%s %s %s', $key, $op, $value);
            $this->where[] = empty($this->where) ? $condition : "$conjunction $condition";
            return;
        }

        // DEFAULT: usar placeholder
        $placeholderKey = str_replace('.', '_', $key) . '_' . count($this->bindings);
        $placeholder = ':' . $placeholderKey;

        $condition = sprintf('%s %s %s', $key, $op, $placeholder);
        $this->where[] = empty($this->where) ? $condition : "$conjunction $condition";
        $this->bindings[$placeholder] = $value;
    }

    private function hydrateModels(array $results): array
    {
        if (!$this->model) {
            return $results;
        }
        return array_map([$this, 'hydrateModel'], $results);
    }

    private function hydrateModel(array $data): object
    {
        if (!$this->model) {
            return (object)$data;
        }

        $model = new $this->model();
        foreach ($data as $key => $value) {
            // Handle aliased columns by using the alias name
            $model->__set($key, $value);
        }
        return $model;
    }
}
