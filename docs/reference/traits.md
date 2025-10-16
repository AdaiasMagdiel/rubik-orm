# 🧩 Model Traits

Rubik ORM's `Model` class is modular and built on top of four **core traits** that separate responsibilities:

- **SchemaTrait** → defines the table structure and lifecycle (create, drop, truncate)
- **CrudTrait** → handles persistence (insert, update, delete)
- **QueryTrait** → provides query and relationship methods
- **SerializationTrait** → controls how models are serialized to arrays or JSON

Each trait can be reused independently in custom base models.

---

## ⚙️ `SchemaTrait`

Defines and manages the **table schema** of the model.

### 🔧 Methods

| Method                                                                | Description                                                     |
| --------------------------------------------------------------------- | --------------------------------------------------------------- |
| `protected static function fields(): array`                           | Must be implemented in the model to define columns.             |
| `public static function primaryKey(): string`                         | Returns the primary key column.                                 |
| `public static function getTableName(): string`                       | Resolves the table name (uses `$table` property or class name). |
| `public static function createTable(bool $ifNotExists = false): bool` | Creates the table based on `fields()`.                          |
| `public static function dropTable(bool $ifExists = false): bool`      | Drops the table completely.                                     |
| `public static function truncateTable(): bool`                        | Deletes all rows but keeps the structure.                       |
| `protected static function getFieldString(array $field): string`      | Generates driver-specific SQL for a column.                     |
| `protected static function escapeDefaultValue(mixed $value): string`  | Escapes default values and `SQL::raw()` expressions.            |

### 🧱 Example

```php
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Column;
use AdaiasMagdiel\Rubik\SQL;

class User extends Model
{
    protected static string $table = 'users';

    protected static function fields(): array
    {
        return [
            'id' => Column::Integer(primaryKey: true, autoincrement: true),
            'name' => Column::Varchar(length: 120, notNull: true),
            'email' => Column::Varchar(length: 200, notNull: true, unique: true),
            'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }
}
```

```php
User::createTable(ifNotExists: true);
```

---

## 💾 `CrudTrait`

Implements basic **Create, Read, Update, Delete** persistence methods.

### 🔧 Methods

| Method                             | Description                                   |
| ---------------------------------- | --------------------------------------------- |
| `save(bool $ignore = false): bool` | Inserts or updates the model automatically.   |
| `update(): bool`                   | Updates only modified fields (`_dirty`).      |
| `delete(): bool`                   | Deletes the current record from the table.    |
| `insertMany(array $records): bool` | Bulk insert multiple rows in one transaction. |

### 🧠 Behavior

- On `save()`, if the primary key is set, it tries to update; otherwise it inserts.
- Tracks dirty fields to avoid redundant updates.
- After insertion, retrieves the auto-incremented ID.

### 🧩 Example

```php
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';
$user->save();

$user->name = 'Alice B.';
$user->save();

$user->delete();
```

---

## 🔍 `QueryTrait`

Provides the **query builder** interface and relationship handling.

### 🔧 Methods

| Method                                            | Description                                  |
| ------------------------------------------------- | -------------------------------------------- |
| `static query(): Query`                           | Returns a new `Query` builder for the model. |
| `static find(mixed $id): ?self`                   | Finds a record by primary key.               |
| `static first(): ?self`                           | Retrieves the first record.                  |
| `static all(): array`                             | Retrieves all records as model instances.    |
| `static paginate(int $page, int $perPage): array` | Returns paginated results.                   |

---

### 🔗 Relationships

| Method                                                                                                                                                | Type                                                     | Description |
| ----------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------- | ----------- |
| `belongsTo(string $related, string $foreignKey, string $ownerKey = 'id')`                                                                             | Defines inverse relation (foreign key on current model). |             |
| `hasOne(string $related, string $foreignKey, string $localKey = 'id')`                                                                                | Defines one-to-one relation.                             |             |
| `hasMany(string $related, string $foreignKey, string $localKey = 'id')`                                                                               | Defines one-to-many relation.                            |             |
| `belongsToMany(string $related, string $pivotTable, string $foreignKey, string $relatedKey, string $localKey = 'id', string $relatedOwnerKey = 'id')` | Defines many-to-many relation via a pivot table.         |             |

---

### 🧩 Example

```php
class Post extends Model
{
    protected static string $table = 'posts';

    protected static function fields(): array
    {
        return [
            'id' => Column::Integer(primaryKey: true, autoincrement: true),
            'user_id' => Column::Integer(
                notNull: true,
                foreignKey: Column::ForeignKey('id', 'users', onDelete: 'CASCADE')['foreign_key']
            ),
            'title' => Column::Varchar(length: 200, notNull: true),
            'body' => Column::Text(),
        ];
    }

    protected static function relationships(): array
    {
        return [
            'user' => [
                'type' => 'belongsTo',
                'related' => User::class,
                'foreignKey' => 'user_id',
                'ownerKey' => 'id',
            ],
        ];
    }
}
```

```php
$post = Post::find(1);
echo $post->user->name;
```

> Relationships are automatically resolved via `__get()` and cached in memory.

---

## 🧮 `SerializationTrait`

Handles **conversion to arrays and JSON**.

All models implement `JsonSerializable` and define `toArray()` and `jsonSerialize()`.

### 🔧 Methods

| Method                   | Description                              |
| ------------------------ | ---------------------------------------- |
| `toArray(): array`       | Returns the model's internal data array. |
| `jsonSerialize(): array` | Defines how it is serialized into JSON.  |

### 🧩 Example

```php
$user = User::find(1);
print_r($user->toArray());
echo json_encode($user);
```

Output:

```json
{
  "id": 1,
  "name": "Alice",
  "email": "alice@example.com",
  "created_at": "2025-10-16 12:34:56"
}
```

> 💡 Maybe you want to reimplement the `toArray()` method
> to handle some custom specifics for your model — e.g., nested relationships or computed fields.

---

## 🧭 Summary

| Trait                | Responsibility                              | Example Use                                   |
| -------------------- | ------------------------------------------- | --------------------------------------------- |
| `SchemaTrait`        | Defines table schema & creates/drops tables | `User::createTable()`                         |
| `CrudTrait`          | Persists model data                         | `$user->save()`                               |
| `QueryTrait`         | Queries and relationships                   | `User::query()->where('active', true)->all()` |
| `SerializationTrait` | Converts model to JSON or array             | `json_encode($user)`                          |

---

> 💡 Each trait is modular — you can extend or override them in your own base model
> to customize persistence, schema generation, or serialization behavior.
