# 🧱 Column

The **`Column`** class provides a _driver-aware_, type-safe way to define table columns in Rubik ORM.  
It adapts automatically to **SQLite** and **MySQL**, enforcing valid type attributes and constraints.

You can define columns directly inside your model’s static `fields()` method, or use it to build schema definitions programmatically.

---

## ⚙️ Overview

Rubik’s `Column` builder:

- Supports over **40 SQL column types**
- Applies **driver-specific mappings** (SQLite, MySQL, PostgreSQL-ready)
- Validates **lengths, precision, defaults, and constraints**
- Supports **raw SQL expressions** via [`SQL::raw()`](./sql.md)
- Provides helper for defining **foreign keys**

---

## 🧩 Importing

```php
use AdaiasMagdiel\Rubik\Column;
use AdaiasMagdiel\Rubik\SQL;
```

---

## 🧱 Basic Usage

Define your columns inside your model:

```php
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Column;

class User extends Model
{
    protected static string $table = 'users';

    protected static function fields(): array
    {
        return [
            'id' => Column::Integer(primaryKey: true, autoincrement: true),
            'name' => Column::Varchar(length: 100, notNull: true),
            'email' => Column::Varchar(length: 150, notNull: true, unique: true),
            'active' => Column::Boolean(default: true),
            'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }
}
```

---

## 🧮 Supported Column Types

Rubik ORM supports a wide range of logical column types,  
automatically mapped to each supported driver (SQLite, MySQL, PostgreSQL-ready).

All type conversions are handled internally according to the active driver.

---

### 🔢 Numeric Types

| Logical Type | SQLite  | MySQL     | PostgreSQL       |
| ------------ | ------- | --------- | ---------------- |
| `INTEGER`    | INTEGER | INTEGER   | INTEGER          |
| `BIGINT`     | INTEGER | BIGINT    | BIGINT           |
| `SMALLINT`   | INTEGER | SMALLINT  | SMALLINT         |
| `TINYINT`    | INTEGER | TINYINT   | SMALLINT         |
| `MEDIUMINT`  | INTEGER | MEDIUMINT | INTEGER          |
| `NUMERIC`    | NUMERIC | NUMERIC   | NUMERIC          |
| `DECIMAL`    | NUMERIC | DECIMAL   | DECIMAL          |
| `REAL`       | REAL    | DOUBLE    | DOUBLE PRECISION |
| `FLOAT`      | REAL    | FLOAT     | DOUBLE PRECISION |
| `DOUBLE`     | REAL    | DOUBLE    | DOUBLE PRECISION |
| `BIT`        | INTEGER | BIT(1)    | BIT(1)           |

---

### 🔁 Serial / Auto-increment Types

| Logical Type  | SQLite  | MySQL                   | PostgreSQL  |
| ------------- | ------- | ----------------------- | ----------- |
| `SERIAL`      | INTEGER | INTEGER AUTO_INCREMENT  | SERIAL      |
| `BIGSERIAL`   | INTEGER | BIGINT AUTO_INCREMENT   | BIGSERIAL   |
| `SMALLSERIAL` | INTEGER | SMALLINT AUTO_INCREMENT | SMALLSERIAL |

> 💡 These are aliases that simplify creating auto-incrementing primary keys.

---

### 📝 Textual Types

| Logical Type | SQLite | MySQL        | PostgreSQL |
| ------------ | ------ | ------------ | ---------- |
| `TEXT`       | TEXT   | TEXT         | TEXT       |
| `VARCHAR`    | TEXT   | VARCHAR(255) | VARCHAR    |
| `CHAR`       | TEXT   | CHAR(1)      | CHAR       |
| `TINYTEXT`   | TEXT   | TINYTEXT     | TEXT       |
| `MEDIUMTEXT` | TEXT   | MEDIUMTEXT   | TEXT       |
| `LONGTEXT`   | TEXT   | LONGTEXT     | TEXT       |
| `JSON`       | TEXT   | JSON         | TEXT       |
| `JSONB`      | TEXT   | JSON         | JSONB      |
| `UUID`       | TEXT   | CHAR(36)     | UUID       |

---

### ⚙️ Boolean / Enum / Set

| Logical Type | SQLite  | MySQL             | PostgreSQL |
| ------------ | ------- | ----------------- | ---------- |
| `BOOLEAN`    | INTEGER | TINYINT(1)        | BOOLEAN    |
| `ENUM`       | TEXT    | ENUM('a','b',...) | TEXT       |
| `SET`        | TEXT    | SET('a','b',...)  | TEXT       |

---

### 📅 Date & Time Types

| Logical Type | SQLite  | MySQL     | PostgreSQL |
| ------------ | ------- | --------- | ---------- |
| `DATE`       | TEXT    | DATE      | DATE       |
| `DATETIME`   | TEXT    | DATETIME  | TIMESTAMP  |
| `TIMESTAMP`  | TEXT    | TIMESTAMP | TIMESTAMP  |
| `TIME`       | TEXT    | TIME      | TIME       |
| `YEAR`       | INTEGER | YEAR      | INTEGER    |

> ⏰ Rubik normalizes precision and time zone handling automatically.  
> For default values, you can safely use `SQL::raw('CURRENT_TIMESTAMP')`.

---

### 💾 Binary / Blob Types

| Logical Type | SQLite | MySQL          | PostgreSQL |
| ------------ | ------ | -------------- | ---------- |
| `BLOB`       | BLOB   | BLOB           | BYTEA      |
| `TINYBLOB`   | BLOB   | TINYBLOB       | BYTEA      |
| `MEDIUMBLOB` | BLOB   | MEDIUMBLOB     | BYTEA      |
| `LONGBLOB`   | BLOB   | LONGBLOB       | BYTEA      |
| `BYTEA`      | BLOB   | LONGBLOB       | BYTEA      |
| `BINARY`     | BLOB   | BINARY(1)      | BYTEA      |
| `VARBINARY`  | BLOB   | VARBINARY(255) | BYTEA      |

> 🧩 All binary types are compatible with `BLOB` internally on SQLite.

---

### 🧭 Spatial / Geometric Types

| Logical Type | SQLite | MySQL      | PostgreSQL |
| ------------ | ------ | ---------- | ---------- |
| `GEOMETRY`   | BLOB   | GEOMETRY   | GEOMETRY   |
| `POINT`      | BLOB   | POINT      | POINT      |
| `LINESTRING` | BLOB   | LINESTRING | LINESTRING |
| `POLYGON`    | BLOB   | POLYGON    | POLYGON    |

> 🌍 Spatial and geometric types are defined for forward-compatibility.  
> They behave as binary columns in SQLite and as native geometry types in Postgres.

---

### 🧩 Notes

- All types are **case-insensitive** when invoked (`Column::integer()`, `Column::INTEGER()`, etc.).
- Validation rules (length, scale, defaults) are automatically enforced.
- If you use an unsupported type name, Rubik throws `BadMethodCallException`.

---

**Example:**

```php
class Location extends Model
{
    protected static string $table = 'locations';

    protected static function fields(): array
    {
        return [
            'id' => Column::BIGSERIAL(primaryKey: true),
            'name' => Column::Varchar(length: 200, notNull: true),
            'coordinates' => Column::POINT(),
            'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }
}
```

---

> 💡 Rubik ORM will automatically generate compatible SQL for the active driver,
> handling type mapping, constraints, and validation under the hood.

---

## ⚙️ Column Options

Each column type can take specific **named arguments**:

| Option          | Type    | Applies To              | Description                   |
| --------------- | ------- | ----------------------- | ----------------------------- |
| `length`        | `int`   | CHAR, VARCHAR, BINARY   | Maximum length                |
| `precision`     | `int`   | DECIMAL, FLOAT, NUMERIC | Total digits                  |
| `scale`         | `int`   | DECIMAL, FLOAT, NUMERIC | Decimal places                |
| `autoincrement` | `bool`  | INTEGER types           | Enables auto increment        |
| `primaryKey`    | `bool`  | All                     | Marks column as primary key   |
| `notNull`       | `bool`  | All                     | Adds NOT NULL                 |
| `unique`        | `bool`  | All                     | Adds UNIQUE constraint        |
| `default`       | `mixed` | All                     | Default value or `SQL::raw()` |
| `unsigned`      | `bool`  | TINYINT, INT, FLOAT     | MySQL-only unsigned modifier  |
| `values`        | `array` | ENUM, SET               | List of allowed values        |
| `foreignKey`    | `array` | Any                     | Defines FK (see below)        |

---

## 🧾 Example — With Foreign Key

```php
use AdaiasMagdiel\Rubik\Column;

class Post extends Model
{
    protected static string $table = 'posts';

    protected static function fields(): array
    {
        return [
            'id' => Column::Integer(primaryKey: true, autoincrement: true),
            'user_id' => Column::Integer(
                notNull: true,
                foreignKey: Column::ForeignKey('id', 'users', 'CASCADE', 'NO ACTION')
            ),
            'title' => Column::Varchar(length: 200, notNull: true),
            'content' => Column::Text(),
        ];
    }
}
```

Generated SQL (SQLite):

```sql
CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    content TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## 🔗 Foreign Key Helper

### `Column::ForeignKey()`

```php
Column::ForeignKey(
    string $references,
    string $table,
    string $onDelete = 'NO ACTION',
    string $onUpdate = 'NO ACTION'
): array
```

Defines a normalized FK structure to merge into a column definition.

| Parameter    | Description                                |
| ------------ | ------------------------------------------ |
| `references` | Column name on the referenced table        |
| `table`      | Target table name                          |
| `onDelete`   | Action on delete (CASCADE, SET NULL, etc.) |
| `onUpdate`   | Action on update                           |

Example:

```php
'user_id' => Column::Integer(
    notNull: true,
    foreignKey: Column::ForeignKey('id', 'users', 'CASCADE', 'NO ACTION')
)
```

---

## 🧰 SQL::raw() Integration

Any default or update value can be a raw SQL literal:

```php
'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
'updated_at' => Column::Datetime(onUpdate: SQL::raw('CURRENT_TIMESTAMP')),
```

Rubik detects `SQL::raw()` automatically and **does not quote** or **bind** it.

---

## 🧪 Validation Rules

Rubik applies **type-specific validation** when building columns:

| Validator              | Ensures                                               |
| ---------------------- | ----------------------------------------------------- |
| `validateStringLength` | CHAR/VARCHAR length between 1–65535                   |
| `validateDecimal`      | DECIMAL precision & scale                             |
| `validateInteger`      | INTEGER default is numeric                            |
| `validateBoolean`      | Default is `true`, `false`, `0`, `1`, or `SQL::raw()` |
| `validateEnum`         | ENUM has valid values and default                     |
| `validateJson`         | Default is valid JSON or `SQL::raw()`                 |
| `validateUuid`         | Default matches RFC 4122                              |
| `validateDateTime`     | DATETIME/TIMESTAMP precision 0–6                      |
| `validateSet`          | SET default matches allowed values                    |

> 💡 Validation happens at definition time, so you’ll catch schema errors before running migrations.

---

## 🧮 Example — Complete Schema

```php
class Product extends Model
{
    protected static string $table = 'products';

    protected static function fields(): array
    {
        return [
            'id' => Column::Integer(primaryKey: true, autoincrement: true),
            'name' => Column::Varchar(length: 120, notNull: true, unique: true),
            'price' => Column::Decimal(precision: 10, scale: 2, default: 0),
            'available' => Column::Boolean(default: true),
            'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }
}
```

---

## ⚙️ Output Example

```sql
CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    price NUMERIC DEFAULT 0,
    available INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

---

## ⚠️ Notes

- Always call `Rubik::connect()` before defining columns.
- Column definitions are driver-aware and validated immediately.
- Use `SQL::raw()` only for trusted, constant expressions.

---

## 📘 Related

- [Rubik Connection](./rubik.md) — Database configuration and DSN setup
- [Model](./model.md) — Integrating column definitions into ORM models
- [SQL](./sql.md) — Embed literal SQL expressions safely
