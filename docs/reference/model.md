# Model — API Reference

Namespace: `AdaiasMagdiel\Rubik`  
Base class for all ORM models.  
Implements persistence, querying, schema definition, and serialization.

---

## Class Definition

```php
abstract class Model implements JsonSerializable
```

### Traits Used

| Trait                | Namespace                                            | Responsibility                         |
| -------------------- | ---------------------------------------------------- | -------------------------------------- |
| `CrudTrait`          | `AdaiasMagdiel\Rubik\Trait\Model\CrudTrait`          | Insert / update / delete operations    |
| `QueryTrait`         | `AdaiasMagdiel\Rubik\Trait\Model\QueryTrait`         | Query builder and relationships        |
| `SchemaTrait`        | `AdaiasMagdiel\Rubik\Trait\Model\SchemaTrait`        | Field definition and schema management |
| `SerializationTrait` | `AdaiasMagdiel\Rubik\Trait\Model\SerializationTrait` | Serialization to array / JSON          |

---

## Static Properties

| Name     | Type     | Description                                                             |
| -------- | -------- | ----------------------------------------------------------------------- |
| `$table` | `string` | Table name for the model. Optional — inferred from class name if unset. |

---

## Instance Properties

| Name              | Type                  | Description                 |
| ----------------- | --------------------- | --------------------------- |
| `$_data`          | `array<string,mixed>` | Field values for the model. |
| `$_dirty`         | `array<string,bool>`  | Tracks modified fields.     |
| `$_relationships` | `array<string,mixed>` | Cached relationship data.   |

---

## Static Methods

### `protected static function fields(): array`

Defines the model’s table fields.
**Must** be implemented in subclasses.

---

### `public static function primaryKey(): string`

Returns the column name marked as `primary_key`.

Throws `RuntimeException` if no primary key is defined.

---

### `public static function getTableName(): string`

Returns the effective table name:

- `$table` property if defined
- or pluralized lowercase class name otherwise.

---

### `public static function query(): Query`

Returns a new `Query` builder instance bound to the model.

---

### `public static function find(mixed $id): ?static`

Finds a model by primary key.
Returns `null` if not found.

---

### `public static function first(): ?static`

Returns the first record.

---

### `public static function all(): array<static>`

Returns all records from the table.

---

### `public static function paginate(int $page, int $perPage): array`

Performs paginated query.

**Return format:**

```php
[
  'data' => array<static>,
  'total' => int,
  'per_page' => int,
  'current_page' => int,
  'last_page' => int
]
```

---

### `public static function insertMany(array $records): bool`

Bulk insert operation.

- Executes all inserts inside a transaction.
- Rolls back on failure.
- Returns `true` if successful.

---

### `public static function createTable(bool $ifNotExists = false): bool`

Creates the model’s table using the definition from `fields()`.

---

### `public static function truncateTable(): bool`

Removes all records while keeping the table structure.

---

### `public static function dropTable(bool $ifExists = false): bool`

Drops the table from the database.

---

## Instance Methods

### `public function __set(string $name, mixed $value): void`

Assigns a field value and marks it dirty if the field exists in `fields()`.

---

### `public function __get(string $key): mixed`

Retrieves:

- A field value from `_data`, or
- A relationship result from `_relationships`.

Automatically resolves relationships defined in `relationships()`.

---

### `protected static function relationships(): array`

Defines model relationships.
Expected format:

```php
[
  'relationName' => [
    'type' => 'belongsTo' | 'hasOne' | 'hasMany' | 'belongsToMany',
    'related' => RelatedModel::class,
    'foreignKey' => 'column',
    'ownerKey' => 'id',
    'pivotTable' => 'pivot_table_name' // for belongsToMany only
  ],
]
```

---

### `public function save(bool $ignore = false): bool`

Inserts or updates the current record.

- Performs **INSERT** if PK not set.
- Performs **UPDATE** if PK exists.
- `$ignore = true` → uses `INSERT OR IGNORE` (SQLite only).

---

### `public function update(): bool`

Updates dirty fields in the database.
Throws if primary key is missing.

---

### `public function delete(): bool`

Deletes the current record.
Throws if primary key is missing.

---

### `public function toArray(): array`

Returns internal data (`$_data`) as an associative array.

---

### `public function jsonSerialize(): array`

Implements `JsonSerializable`.
Equivalent to `toArray()`.

---

### Relationship Helpers

| Method          | Signature                                                                                                                                       | Description                                   |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| `belongsTo`     | `(string $related, string $foreignKey, string $ownerKey = 'id'): Query`                                                                         | Defines inverse (many→one) relation           |
| `hasOne`        | `(string $related, string $foreignKey, string $localKey = 'id'): Query`                                                                         | Defines one-to-one relation                   |
| `hasMany`       | `(string $related, string $foreignKey, string $localKey = 'id'): Query`                                                                         | Defines one-to-many relation                  |
| `belongsToMany` | `(string $related, string $pivotTable, string $foreignKey, string $relatedKey, string $localKey = 'id', string $relatedOwnerKey = 'id'): Query` | Defines many-to-many relation via pivot table |

---

## Exceptions

| Exception                  | Condition                                                     |
| -------------------------- | ------------------------------------------------------------- |
| `RuntimeException`         | When required configuration (connection, PK, etc.) is missing |
| `InvalidArgumentException` | When invalid arguments or relationships are provided          |

---

## Related Classes

| Class                   | Description                                      |
| ----------------------- | ------------------------------------------------ |
| [`Query`](./query.md)   | Query builder returned by `query()`.             |
| [`Column`](./column.md) | Column definition builder used in `fields()`.    |
| [`SQL`](../sql-raw.md)  | Raw SQL expression wrapper for literal defaults. |
