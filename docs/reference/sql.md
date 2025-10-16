# 🧩 SQL Raw Expressions

The `SQL` value object allows you to embed **literal SQL expressions** directly in your schema or queries.  
It is the official and safe way to express **driver-specific SQL functions** without Rubik escaping or binding them.

---

## ⚙️ Overview

Normally, Rubik ORM **quotes and binds** all values to prevent SQL injection.  
However, in some cases — such as `CURRENT_TIMESTAMP`, `NOW()`, `UUID()` or `gen_random_uuid()` —  
you need to embed a literal SQL expression **verbatim**.

That’s exactly what `SQL::raw()` is for.

```php
use AdaiasMagdiel\Rubik\SQL;

$expr = SQL::raw('CURRENT_TIMESTAMP');
```

When Rubik detects this type, it injects the expression as-is into the generated SQL,
without adding quotes, parameters, or bindings.

---

## 🧱 When to Use

You can safely use `SQL::raw()` in:

| Context              | Description                                                       |
| -------------------- | ----------------------------------------------------------------- |
| **Column defaults**  | Define literal defaults such as timestamps or UUIDs               |
| **Query conditions** | Use SQL functions inside `WHERE` or `UPDATE` clauses              |
| **Updates/Inserts**  | Set system-generated values like `NOW()` or `datetime("now")`     |
| **Schema builder**   | Add raw defaults or computed values directly in field definitions |

---

## 🧮 Example – Column Default

```php
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
            'updated_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }
}
```

Generated SQL (SQLite):

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🔍 Example – In Queries

You can include raw SQL inside queries seamlessly:

```php
use AdaiasMagdiel\Rubik\SQL;

$users = User::query()
    ->select([
        'id',
        'name',
        SQL::raw('LENGTH(name) AS name_length'),
    ])
    ->all();
```

SQL generated:

```sql
SELECT id, name, LENGTH(name) AS name_length FROM users;
```

---

### Raw Conditions

```php
User::query()
    ->where(SQL::raw('DATE(created_at)'), '>=', SQL::raw('DATE("2025-01-01")'))
    ->all();
```

SQL generated:

```sql
SELECT * FROM users WHERE DATE(created_at) >= DATE("2025-01-01");
```

> 💡 Rubik automatically detects `SQL` instances and injects them unescaped.

---

## ✏️ Example – Updates with Raw SQL

```php
User::query()
    ->where('active', true)
    ->update(['updated_at' => SQL::raw('CURRENT_TIMESTAMP')]);
```

SQL generated:

```sql
UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE active = 1;
```

---

## 🧩 Example – Inserts with Raw SQL

```php
User::insert([
    'name' => 'Anonymous',
    'email' => 'anon@example.com',
    'created_at' => SQL::raw('datetime("now")'),
]);
```

Rubik detects the raw SQL expression and includes it directly, without binding.

---

## ⚙️ Internals

The `SQL` class is an **immutable value object**:

```php
final class SQL
{
    private string $expr;

    public function __construct(string $expr)
    {
        $this->expr = $expr;
    }

    public function __toString(): string
    {
        return $this->expr;
    }

    public static function raw(string $expr): static
    {
        return new static($expr);
    }
}
```

Internally, Rubik checks:

```php
if ($value instanceof SQL) {
    // Inject expression as-is
}
```

This logic is used consistently in:

- `Column` validators and defaults
- `Query` builder (`update`, `where`, `select`)
- `SchemaTrait` during table creation

---

## 🧱 Example – Full Model with Raw Defaults

```php
use AdaiasMagdiel\Rubik\Column;
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\SQL;

class Product extends Model
{
    protected static string $table = 'products';

    protected static function fields(): array
    {
        return [
            'id' => Column::Integer(primaryKey: true, autoincrement: true),
            'name' => Column::Varchar(length: 255, notNull: true),
            'sku' => Column::Varchar(length: 50, unique: true),
            'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
            'updated_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }
}
```

---

## 🧾 Summary

| Context        | Example                                                                       | SQL Output                  |
| -------------- | ----------------------------------------------------------------------------- | --------------------------- |
| Column default | `Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP'))`                    | `DEFAULT CURRENT_TIMESTAMP` |
| Query select   | `SQL::raw('COUNT(*) AS total')`                                               | Adds computed columns       |
| WHERE clause   | `->where(SQL::raw('DATE(created_at)'), '>=', SQL::raw('DATE("2025-01-01")'))` | Unquoted expressions        |
| Update         | `->update(['updated_at' => SQL::raw('CURRENT_TIMESTAMP')])`                   | Literal update              |
| Insert         | `'created_at' => SQL::raw('datetime("now")')`                                 | Raw expression              |

---

## ⚠️ Security Notes

`SQL::raw()` is **powerful** but must be used carefully.

✅ Safe examples:

- System constants like `CURRENT_TIMESTAMP`, `NOW()`
- Internal SQL functions: `LENGTH()`, `DATE()`, `UUID()`

🚫 Unsafe examples:

- Concatenating user input into `SQL::raw()`
- Using unvalidated external data

> ⚠️ Never pass user input directly to `SQL::raw()`.
> It disables escaping, making your query vulnerable to SQL injection.

---

## 🧭 See Also

- [Column Reference](./column.md) — define types and defaults with SQL expressions
- [Queries](../queries.md) — use raw SQL inside select and where clauses

---

> 💡 _If you need full control over SQL fragments, `SQL::raw()` is your trusted escape hatch — minimal, explicit, and safe when used with care._
