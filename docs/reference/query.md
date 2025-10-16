# Query — API Reference

Namespace: `AdaiasMagdiel\Rubik`  
Provides a fluent query builder for Rubik ORM models.  
Supports `SELECT`, `UPDATE`, `DELETE`, filtering, joins, grouping, ordering, and pagination.

---

## Class Definition

```php
class Query
```

---

## Properties

| Name         | Type                  | Description                                            |
| ------------ | --------------------- | ------------------------------------------------------ |
| `$model`     | `string`              | Fully qualified class name of the model being queried. |
| `$table`     | `string`              | Table name of the query.                               |
| `$operation` | `string`              | SQL operation type (`SELECT`, `UPDATE`, `DELETE`).     |
| `$select`    | `array<int,string>`   | Columns to select.                                     |
| `$where`     | `array<int,string>`   | WHERE conditions.                                      |
| `$bindings`  | `array<string,mixed>` | Bound parameters for prepared statements.              |
| `$orderBy`   | `array<int,string>`   | ORDER BY clauses.                                      |
| `$groupBy`   | `array<int,string>`   | GROUP BY columns.                                      |
| `$having`    | `array<int,string>`   | HAVING conditions.                                     |
| `$joins`     | `array<int,string>`   | JOIN clauses.                                          |
| `$limit`     | `int`                 | LIMIT value (default -1 = none).                       |
| `$offset`    | `int`                 | OFFSET value (default -1 = none).                      |

---

## Methods

### `public function setTable(string $table): self`

Sets the target table name.
Returns the query instance for chaining.

---

### `public function setModel(string $model): self`

Associates a model class with the query.
Automatically sets `$table` using `Model::getTableName()`.

Throws `RuntimeException` if the class does not exist.

---

### `public function select(string|array $fields = '*'): self`

Defines columns to select.

- Accepts string or array.
- Automatically includes the model’s primary key.
- Preserves `AS` aliases.

---

### `public function where(string $key, mixed $operatorOrValue, mixed $value = null): self`

Adds a `WHERE` condition joined by `AND`.

Equivalent to:

```sql
WHERE key = value
```

or

```sql
WHERE key operator value
```

---

### `public function orWhere(string $key, mixed $operatorOrValue, mixed $value = null): self`

Adds a `WHERE` condition joined by `OR`.

---

### `public function whereIn(string $key, array $values): self`

Adds a `WHERE ... IN (...)` clause.
Throws `InvalidArgumentException` if the array is empty.

---

### `public function join(string $table, string $left, string $op, string $right): self`

Adds an `INNER JOIN` clause.

---

### `public function leftJoin(string $table, string $left, string $op, string $right): self`

Adds a `LEFT JOIN` clause.

---

### `public function rightJoin(string $table, string $left, string $op, string $right): self`

Adds a `RIGHT JOIN` clause.

---

### `public function orderBy(string $column, string $direction = 'ASC'): self`

Adds an `ORDER BY` clause.
`$direction` must be `ASC` or `DESC`.

---

### `public function groupBy(string $column): self`

Adds a `GROUP BY` clause.

---

### `public function having(string $condition): self`

Adds a `HAVING` clause condition.

---

### `public function limit(int $limit): self`

Sets the `LIMIT` clause.
Throws `InvalidArgumentException` if negative.

---

### `public function offset(int $offset): self`

Sets the `OFFSET` clause.
Throws `InvalidArgumentException` if negative.

---

### `public function delete(): bool`

Executes a `DELETE FROM ...` query.

Throws `RuntimeException` if statement preparation fails.

---

### `public function update(array $data): bool`

Executes an `UPDATE ... SET ...` query.

- Automatically binds parameters.
- Accepts `SQL::raw()` values (inserted verbatim).
- Returns `true` on success.

---

### `public function paginate(int $page, int $perPage): array`

Executes paginated query and returns a pagination array:

```php
[
  'data' => array<object>,
  'total' => int,
  'per_page' => int,
  'current_page' => int,
  'last_page' => int,
]
```

Throws `InvalidArgumentException` if `$page` or `$perPage` < 1.

---

### `public function count(): int`

Counts total matching records.
Internally issues a `SELECT COUNT(*)` query.

---

### `public function all(): array`

Executes the query and returns all results.
Results are hydrated into model instances if `$model` is set.

---

### `public function first(): ?object`

Executes the query and returns the first result (or `null` if none).
Automatically sets `LIMIT 1`.

---

### `public function exec(): bool`

Executes the built query and returns `true` if any rows were affected.

---

### `public function getSql(): string`

Returns the raw SQL string built by the query.

Throws `RuntimeException` if:

- table not set, or
- attempting to get SQL for an `UPDATE` operation directly.

---

## Protected / Internal Methods

| Method                                                                                            | Description                                                  |
| ------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| `private function executeStatement(): PDOStatement`                                               | Prepares and executes the SQL with bound parameters.         |
| `private function addCondition(string $key, mixed $value, string $op, string $conjunction): void` | Adds a condition to the `WHERE` clause. Supports `SQL::raw`. |
| `private function buildJoinsClause(): string`                                                     | Builds the `JOIN` section.                                   |
| `private function buildWhereClause(): string`                                                     | Builds the `WHERE` section.                                  |
| `private function buildGroupByClause(): string`                                                   | Builds the `GROUP BY` section.                               |
| `private function buildHavingClause(): string`                                                    | Builds the `HAVING` section.                                 |
| `private function buildOrderByClause(): string`                                                   | Builds the `ORDER BY` section.                               |
| `private function buildLimitClause(): string`                                                     | Builds the `LIMIT` clause.                                   |
| `private function buildOffsetClause(): string`                                                    | Builds the `OFFSET` clause.                                  |
| `private function hydrateModels(array $results): array`                                           | Hydrates array results into model instances.                 |
| `private function hydrateModel(array $data): object`                                              | Instantiates and populates a model from result data.         |

---

## Exceptions

| Exception                  | Condition                                              |
| -------------------------- | ------------------------------------------------------ |
| `RuntimeException`         | Missing table, failed statement, or invalid operation. |
| `InvalidArgumentException` | Invalid parameters, operators, or pagination.          |

---

## Related Classes

| Class                  | Description                                       |
| ---------------------- | ------------------------------------------------- |
| [`Model`](./model.md)  | Provides the base model and relationship methods. |
| [`SQL`](../sql-raw.md) | Wraps literal SQL fragments for safe injection.   |
