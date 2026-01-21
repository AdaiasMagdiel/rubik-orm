# Rubik ORM

**Rubik** is a strict, type-safe, and driver-agnostic Object-Relational Mapper (ORM) for PHP 8.1+. It implements the **Active Record** pattern but distinctively separates schema definition (`SchemaTrait`), query construction (`Query`), and persistence (`CrudTrait`).

## Architectural Philosophy

Rubik was built to solve specific problems found in heavier ORMs:

1.  **Code-First Truth:** The database schema is defined _inside_ the Model via the `fields()` method. There are no separate migration files that can drift out of sync with your classes.
2.  **Strict Validation:** The schema builder (`Column` class) validates types, lengths, and defaults _before_ generating SQL.
3.  **Driver Abstraction:** You define a column as `Column::Boolean()`. Rubik decides if that becomes `TINYINT(1)` (MySQL) or `INTEGER` (SQLite) at runtime.
4.  **Zero-Dependency:** It relies solely on `ext-pdo`.

## The Core Components

| Component      | Responsibility                                                                       |
| :------------- | :----------------------------------------------------------------------------------- |
| **`Rubik`**    | The singleton connection manager. Handles `PDO` instantiation and transaction state. |
| **`Model`**    | The entry point. Uses traits to compose capabilities (CRUD, Schema, Querying).       |
| **`Query`**    | A fluent SQL builder. Handles sanitization, parameter binding, and hydration.        |
| **`Column`**   | A meta-programming factory. Validates and normalizes field definitions.              |
| **`Relation`** | Abstract logic for connecting models (HasOne, BelongsTo, etc.).                      |

## Requirement Checklist

- **PHP:** 8.1 or higher.
- **Extensions:** `ext-pdo`, plus `ext-pdo_sqlite` or `ext-pdo_mysql`.
- **Database:**
  - MySQL 5.7+ or MariaDB 10.2+
  - SQLite 3.25+ (Required for proper Foreign Key support)

## Basic Usage Teaser

```php
use App\Models\User;

// 1. Definition
class User extends Model {
    protected static function fields(): array {
        return [
            'id'   => Column::Integer(primaryKey: true, autoIncrement: true),
            'name' => Column::Varchar(length: 100, notNull: true)
        ];
    }
}

// 2. Synchronization
User::createTable(ifNotExists: true);

// 3. Interaction
$user = new User();
$user->name = "Adaías";
$user->save();
```
