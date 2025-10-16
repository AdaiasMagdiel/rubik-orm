# SQL Raw Expressions

The `AdaiasMagdiel\Rubik\SQL` class is a **value object** representing a literal SQL fragment that must be inserted into generated SQL **verbatim**, without quoting or binding.

This is useful when you need to use SQL functions, constants, or expressions such as `CURRENT_TIMESTAMP`, `NOW()`, `gen_random_uuid()`, or similar — anywhere Rubik would otherwise escape or bind a value.

---

## 🧩 Importing

```php
use AdaiasMagdiel\Rubik\SQL;
```

---

## ⚙️ Purpose

`SQL::raw()` lets you embed literal SQL fragments in:

- **Column definitions** (e.g., `DEFAULT CURRENT_TIMESTAMP`)
- **Update queries**
- **Where conditions**
- **Schema constraints**

Everywhere else, Rubik escapes and binds values normally — this object simply signals _"do not quote this value"_.

---

## 🧱 In Column Definitions

The `Column` builder fully supports `SQL::raw()` for literal defaults or `onUpdate` clauses.

Example:

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

✅ All validators in `Column` accept `SQL::raw()` wherever literal defaults are allowed.

---

## 🔍 In Queries

`SQL::raw()` can be used in queries through the `Query` builder.

### Example — computed columns

```php
$users = User::query()
    ->select([
        'id',
        'name',
        SQL::raw('LENGTH(name) AS name_length'),
    ])
    ->all();
```

Generated SQL:

```sql
SELECT id, name, LENGTH(name) AS name_length FROM users;
```

---

### Example — WHERE conditions

```php
User::query()
    ->where(SQL::raw('DATE(created_at)'), '>=', SQL::raw('DATE("2025-01-01")'))
    ->all();
```

Generated SQL:

```sql
SELECT * FROM users WHERE DATE(created_at) >= DATE("2025-01-01");
```

---

## ✏️ In Update Statements

You can safely use `SQL::raw()` when updating with expressions.

```php
User::query()
    ->where('id', 1)
    ->update(['updated_at' => SQL::raw('CURRENT_TIMESTAMP')]);
```

Generated SQL:

```sql
UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = :id_0;
```

Rubik will detect the `SQL` object and inject the raw fragment directly instead of binding it.

---

## 🧮 In Model `save()` and `update()`

When you assign `SQL::raw()` to a model field and call `save()` or `update()`, Rubik also respects it:

```php
$user = User::find(1);
$user->updated_at = SQL::raw('CURRENT_TIMESTAMP');
$user->save();
```

→ The ORM will generate an `UPDATE` that includes `CURRENT_TIMESTAMP` verbatim.

---

## 🧰 How It Works

The `SQL` class is a simple immutable value object:

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

Internally, Rubik checks if a value is an instance of `AdaiasMagdiel\Rubik\SQL`.
If it is, Rubik converts it to a string and **skips parameter binding**.

Places that handle this:

- `Column` validation methods (`validateDecimal`, `validateInteger`, etc.)
- `SchemaTrait::escapeDefaultValue()`
- `Query::addCondition()` (WHERE clauses)
- `Query::update()` (SET clauses)

---

## ⚠️ Security Warning

> `SQL::raw()` **completely bypasses quoting and escaping**.
> Never use it with user input or untrusted data.

Safe examples ✅:

```php
SQL::raw('CURRENT_TIMESTAMP')
SQL::raw('LENGTH(name)')
SQL::raw('gen_random_uuid()')
```

Unsafe examples 🚫:

```php
SQL::raw("name = '{$_GET['name']}'")  // ❌ Injection risk
SQL::raw($_POST['query'])              // ❌ Dangerous
```

---

## 🧭 Summary

| Context           | Example                                                                       | Behavior              |
| ----------------- | ----------------------------------------------------------------------------- | --------------------- |
| Column default    | `Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP'))`                    | Default literal value |
| Schema onUpdate   | `Column::Datetime(onUpdate: SQL::raw('CURRENT_TIMESTAMP'))`                   | Literal ON UPDATE     |
| Query select      | `SQL::raw('LENGTH(name) AS len')`                                             | Computed column       |
| Query where       | `->where(SQL::raw('DATE(created_at)'), '>=', SQL::raw('DATE("2025-01-01")'))` | Raw condition         |
| Query update      | `->update(['updated_at' => SQL::raw('CURRENT_TIMESTAMP')])`                   | Unquoted expression   |
| Model save/update | `$model->field = SQL::raw('NOW()')`                                           | Injects raw SQL value |
