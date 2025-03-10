<?php

namespace AdaiasMagdiel\Rubik;

use Exception;
use PDO;

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

    public function __construct(string $model, string $table)
    {
        $this->model = $model;
        $this->table = $table;
    }

    public function select(string|array $fields): self
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $pk = $this->model::primaryKey();
        $fields = array_unique(array_merge([$pk], $fields));

        $this->select = array_unique(array_merge($this->select, $fields));

        return $this;
    }

    private function addCondition(string $key, mixed $value, string $op, string $conjunction): void
    {
        $placeholder = ":$key";
        $condition = "$key $op $placeholder";

        if (empty($this->where)) {
            $this->where[] = "WHERE $condition";
        } else {
            $this->where[] = "$conjunction $condition";
        }

        $this->bindings[$placeholder] = $value;
    }

    public function where(string $key, mixed $value, string $op = "="): self
    {
        $this->addCondition($key, $value, $op, 'AND');
        return $this;
    }

    public function whereOr(string $key, mixed $value, string $op = "="): self
    {
        $this->addCondition($key, $value, $op, 'OR');
        return $this;
    }

    public function whereIn(string $key, array $values): self
    {
        if (empty($values)) {
            throw new Exception("whereIn requires a non-empty array");
        }

        $paramNames = [];
        foreach ($values as $index => $value) {
            $param = ":{$key}_{$index}";
            $paramNames[] = $param;
            $this->bindings[$param] = $value;
        }

        $clause = implode(', ', $paramNames);
        $condition = "IN ($clause)";

        if (empty($this->where)) {
            $this->where[] = "WHERE $key $condition";
        } else {
            $this->where[] = "AND $key $condition";
        }

        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new Exception("Invalid limit: must be a positive integer");
        }

        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new Exception("Invalid offset: must be a non-negative integer");
        }

        if ($this->limit === -1) {
            throw new Exception("Offset requires a limit to be set first");
        }

        $this->offset = $offset;
        return $this;
    }

    public function all(): array
    {
        $statement = $this->makeStatement();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($item) {
            $model = new $this->model();
            foreach ($item as $key => $value) {
                $model->__set($key, $value);
            }
            return $model;
        }, $result ?: []);
    }

    public function first(): ?object
    {
        $statement = $this->makeStatement();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        $model = new $this->model();
        foreach ($result as $key => $value) {
            $model->__set($key, $value);
        }

        return $model;
    }

    private function makeStatement(): \PDOStatement
    {
        $pdo = Rubik::getConn();
        $query = $this->makeQuery();

        $statement = $pdo->prepare($query);
        if (!$statement) {
            $error = $pdo->errorInfo();
            throw new Exception("Failed to prepare query: {$query}\nError: {$error[2]}");
        }

        $statement->execute($this->bindings);
        return $statement;
    }

    private function makeQuery(): string
    {
        $sqlParts = [];

        if ($this->operation === "SELECT") {
            $sqlParts[] = "SELECT";
            $sqlParts[] = empty($this->select) ? "*" : implode(', ', $this->select);
            $sqlParts[] = "FROM";
            $sqlParts[] = $this->table;
        }

        if (!empty($this->where)) {
            $sqlParts[] = implode(' ', $this->where);
        }

        if ($this->limit !== -1) {
            $sqlParts[] = "LIMIT {$this->limit}";
        }

        if ($this->offset !== -1) {
            $sqlParts[] = "OFFSET {$this->offset}";
        }

        return implode(' ', $sqlParts);
    }
}
