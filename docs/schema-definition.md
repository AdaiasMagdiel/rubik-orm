# Schema Definition & Migrations

Rubik uses a powerful "Code-First" approach. You define your schema using the `fields()` method within your Model. This allows you to generate, truncate, and drop tables programmatically.

## The `fields()` Method

Every model must implement this method. It returns an associative array where:

- **Key:** The column name in the database.
- **Value:** An array definition returned by the `Column` class helper.

```php
protected static function fields(): array
{
    return [
        'id'         => Column::Integer(primaryKey: true, autoIncrement: true),
        'created_at' => Column::DateTime(default: SQL::raw('CURRENT_TIMESTAMP')),
    ];
}
```

## The `Column` Factory

The `AdaiasMagdiel\Rubik\Column` class is a smart factory. It validates your inputs and maps logical types to driver-specific SQL types.

### Available Types & Logic

| Method     | SQLite Mapping | MySQL Mapping  | Validation Rules                      |
| :--------- | :------------- | :------------- | :------------------------------------ |
| `Integer`  | `INTEGER`      | `INTEGER`      | Default must be int.                  |
| `BigInt`   | `INTEGER`      | `BIGINT`       | -                                     |
| `TinyInt`  | `INTEGER`      | `TINYINT`      | Range check (-128 to 127 or 0-255).   |
| `Decimal`  | `NUMERIC`      | `DECIMAL(p,s)` | Validates precision/scale/max values. |
| `Boolean`  | `INTEGER`      | `TINYINT(1)`   | Default must be boolean or 0/1.       |
| `Varchar`  | `TEXT`         | `VARCHAR(n)`   | Length 1-65535.                       |
| `Text`     | `TEXT`         | `TEXT`         | -                                     |
| `Enum`     | `TEXT`         | `ENUM(...)`    | `values` array cannot be empty.       |
| `Json`     | `TEXT`         | `JSON`         | Validates default JSON structure.     |
| `Uuid`     | `TEXT`         | `CHAR(36)`     | Validates RFC 4122 format.            |
| `DateTime` | `TEXT`         | `DATETIME`     | Precision 0-6.                        |

### Common Arguments

All `Column` methods accept these named arguments:

- `primaryKey` (bool): Marks column as PK.
- `notNull` (bool): Adds `NOT NULL`.
- `unique` (bool): Adds `UNIQUE` constraint.
- `default` (mixed): The default value (literal or `SQL::raw`).
- `autoIncrement` (bool): For integer types.

### Foreign Keys

Use the `Column::ForeignKey` helper to define relations inside the column definition.

```php
'user_id' => Column::Integer(
    notNull: true,
    foreignKey: Column::ForeignKey(
        references: 'id',
        table: 'users',
        onDelete: 'CASCADE',
        onUpdate: 'NO ACTION'
    )
)
```

!!! note "Driver Awareness"
SQLite creates Foreign Keys inline during `CREATE TABLE`. If you are using SQLite, make sure your tables are created in the correct order (Parents before Children) to satisfy constraint checks.

## DDL Operations

The `SchemaTrait` provides static methods to manipulate the database structure based on your definition.

### Create Table

```php
// Generates CREATE TABLE IF NOT EXISTS users (...)
User::createTable(ifNotExists: true);
```

### Drop Table

```php
// Generates DROP TABLE IF EXISTS users
User::dropTable(ifExists: true);
```

### Truncate Table

Provides a unified interface for clearing data.

- **MySQL:** Executes `TRUNCATE TABLE`.
- **SQLite:** Executes `DELETE FROM` (as TRUNCATE is not supported) and optimizes `autoincrement`.

```php
User::truncateTable();
```
