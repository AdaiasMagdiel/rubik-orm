# Models

Models in **Rubik ORM** represent database tables and act as structured, object-oriented interfaces for your data.

Each model defines its **table name**, **schema fields**, and optionally **relationships** to other models.

Rubik models are **lightweight**, **driver-aware**, and rely directly on **PDO** for efficiency and portability across **SQLite** and **MySQL**.

---

## 🧱 Defining a Model

All models must extend the base `AdaiasMagdiel\Rubik\Model` class and implement the `fields()` method.

A model usually defines:

- The static `$table` property → name of the database table
- The `fields()` method → columns and their types
- (Optionally) the `relationships()` method → associations with other models

Example:

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
            'name' => Column::Varchar(length: 100, notNull: true),
            'email' => Column::Varchar(length: 150, notNull: true, unique: true),
            'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }
}
```

---

## ⚙️ Table Naming Convention

If you don’t specify `$table`, Rubik automatically uses the **plural, lowercase form** of the class name:

```php
class Product extends Model {}

echo Product::getTableName(); // "products"
```

> 🟣 It’s a good practice to explicitly define `$table` for clarity.

---

## 🧩 Field Definitions

The `fields()` method defines a mapping of column names to `Column` definitions.

Each column type is created using `Column::Type()`, with named arguments for attributes.

### Example

```php
'price' => Column::Decimal(precision: 10, scale: 2, default: 0.00, notNull: true),
'status' => Column::Enum(values: ['active', 'inactive'], default: 'active'),
'metadata' => Column::Json(default: '{}'),
```

### Available Attributes

| Attribute            | Type    | Description                                        |
| -------------------- | ------- | -------------------------------------------------- |
| `primaryKey`         | `bool`  | Marks the field as primary key                     |
| `autoincrement`      | `bool`  | Enables auto-increment (driver-specific)           |
| `notNull`            | `bool`  | Adds `NOT NULL` constraint                         |
| `unique`             | `bool`  | Adds `UNIQUE` constraint                           |
| `default`            | `mixed` | Default value (may use `SQL::raw()` for functions) |
| `length`             | `int`   | Character limit for strings                        |
| `precision`, `scale` | `int`   | For numeric types                                  |
| `values`             | `array` | For ENUM or SET values                             |
| `foreignKey`         | `array` | Foreign key definition via `Column::ForeignKey()`  |

---

## 🔗 Foreign Keys

Rubik supports relational constraints through the `Column::ForeignKey()` helper.
You can attach it **directly to a column** using the `foreignKey:` argument.

### Example

```php
'user_id' => Column::Integer(
    notNull: true,
    foreignKey: Column::ForeignKey('id', 'users', 'CASCADE', 'CASCADE')
),
```

This generates a foreign key constraint equivalent to:

```sql
FOREIGN KEY (user_id)
  REFERENCES users(id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
```

Under the hood, `Column::ForeignKey()` returns a small associative array like:

```php
[
  'foreign_key' => [
    'references' => 'id',
    'table'      => 'users',
    'on_delete'  => 'CASCADE',
    'on_update'  => 'CASCADE'
  ]
]
```

Rubik merges that automatically into the field definition during table creation.

---

## 🧰 Table Management

Once defined, you can create or destroy tables directly from your model:

```php
User::createTable(ifNotExists: true);  // Creates table if not exists
User::truncateTable();                 // Clears all rows
User::dropTable(ifExists: true);       // Drops the table
```

Rubik automatically handles SQLite’s `PRAGMA foreign_keys = ON` when needed.

---

## 💾 Inserting Data

To insert new records, simply create an instance and call `save()`:

```php
$user = new User();
$user->name = 'Adaías Magdiel';
$user->email = 'adaias@example.com';
$user->save();
```

Rubik will automatically:

- Perform an `INSERT` if the record doesn’t exist
- Perform an `UPDATE` if it does
- Update the model’s primary key (`id`) after insert

You can also bulk insert:

```php
User::insertMany([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
]);
```

---

## ✏️ Updating Data

To update a record, fetch it, modify attributes, and save again:

```php
$user = User::find(1);
$user->name = 'Updated Name';
$user->save();
```

Rubik automatically tracks which fields changed (the `_dirty` map) and only updates those columns.

---

## 🗑️ Deleting Records

```php
$user = User::find(1);
$user->delete();
```

---

## 🔍 Querying Data

Each model comes with query methods powered by the internal `Query` builder:

```php
User::all();         // Returns all users
User::find(5);       // Finds by primary key
User::first();       // Gets the first record
```

For advanced queries:

```php
$users = User::query()
    ->where('email', 'LIKE', '%@gmail.com')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->all();
```

---

## 🧭 Pagination

Paginate records using:

```php
$page = User::paginate(page: 2, perPage: 10);

print_r($page);

/*
[
  'data' => [...],
  'total' => 54,
  'per_page' => 10,
  'current_page' => 2,
  'last_page' => 6
]
*/
```

---

## 🤝 Relationships

Rubik supports four relationship types:

| Type            | Description                                                 |
| --------------- | ----------------------------------------------------------- |
| `belongsTo`     | The model belongs to another (foreign key on current model) |
| `hasOne`        | The model has one related record                            |
| `hasMany`       | The model has many related records                          |
| `belongsToMany` | Many-to-many through a pivot table                          |

### Example

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
                foreignKey: Column::ForeignKey('id', 'users', 'CASCADE', 'CASCADE')
            ),
            'title' => Column::Varchar(length: 200, notNull: true),
            'content' => Column::Text(),
            'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }

    protected static function relationships(): array
    {
        return [
            'author' => [
                'type' => 'belongsTo',
                'related' => User::class,
                'foreignKey' => 'user_id',
                'ownerKey' => 'id'
            ],
        ];
    }
}
```

Usage:

```php
$post = Post::find(1);
echo $post->author->name;
```

Rubik resolves relationships lazily and caches them per model instance.

---

## 🧮 Serialization

All models implement `JsonSerializable`, so they can be directly converted to arrays or JSON.

```php
$user = User::find(1);
print_r($user->toArray());
echo json_encode($user);
```

Output:

```json
{
  "id": 1,
  "name": "Adaías Magdiel",
  "email": "adaias@example.com",
  "created_at": "2025-10-16 12:34:56"
}
```

> 💡 **Tip:** You may want to reimplement the `toArray()` method in your model
> if you need to handle some custom specifics — for example, hiding sensitive fields,
> formatting timestamps, or adding computed attributes.

---

## 🧠 Dirty Tracking

Rubik automatically tracks which fields have been changed since the last save:

```php
$user = User::find(1);
$user->name = 'Changed';
$user->save(); // Only updates 'name'
```

---

## ⚡ Hydration

Query results are automatically **hydrated** into fully functional model instances:

```php
$users = User::query()->where('id', '<', 5)->all();

foreach ($users as $user) {
    echo $user->name;
}
```

Each instance has full access to `save()`, `delete()`, `toArray()`, and relationships.

---

## 🧾 Best Practices

✅ Always define fields explicitly in `fields()`
✅ Use `SQL::raw()` for literal SQL expressions (e.g., `CURRENT_TIMESTAMP`)
✅ Always enable foreign keys in SQLite (Rubik does this for you)
✅ Keep table names lowercase and plural
✅ Use clear and descriptive field names

---

## 🧭 Next Steps

Continue with:

- [Query Builder](./queries.md) → Fluent SQL generation
- [Relationships](./relationships.md) → Detailed guide on associations
