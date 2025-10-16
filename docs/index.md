# Rubik ORM

> **Rubik ORM** is a lightweight, driver-aware Object–Relational Mapper for PHP that seamlessly adapts to **SQLite** and **MySQL/MariaDB** environments — providing a fluent, expressive, and safe abstraction for database modeling, querying, and schema management.

---

## 🚀 Overview

Rubik ORM brings the power of modern ORMs to a **compact and dependency-free** package.  
It is designed to be fast, predictable, and driver-aware — automatically adapting SQL syntax and behavior depending on the active database driver.

### ✨ Key Features

- **Driver-aware design** — supports both **SQLite** and **MySQL/MariaDB**, automatically adjusting types and syntax.
- **Lightweight and dependency-free** — built with native **PDO** under the hood.
- **Schema builder** — programmatically define and create database schemas in PHP.
- **Query builder** — build expressive SQL queries fluently with chainable methods.
- **Model abstraction** — define models that map directly to database tables.
- **Relationships** — define `belongsTo`, `hasOne`, `hasMany`, and `belongsToMany` associations.
- **Type-safe columns** — via `Column` definitions and extensive validation.
- **Raw SQL support** — safely inject raw SQL fragments using `SQL::raw()`.
- **Portable** — works seamlessly across CLI scripts, REST APIs, and traditional web apps.
- **Test-friendly** — designed to work easily with in-memory SQLite databases.

---

## 🧩 Example

Here's a minimal example of Rubik ORM in action:

```php
<?php

use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Column;
use AdaiasMagdiel\Rubik\SQL;

// 1. Connect to an in-memory SQLite database
Rubik::connect(Driver::SQLITE, path: ':memory:');

// 2. Define a model
class User extends Model {
    protected static string $table = 'users';

    protected static function fields(): array {
        return [
            'id' => Column::Integer(primaryKey: true, autoincrement: true),
            'name' => Column::Varchar(length: 100, notNull: true),
            'email' => Column::Varchar(length: 150, notNull: true, unique: true),
            'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }
}

// 3. Create the table
User::createTable();

// 4. Create and save a user
$user = new User();
$user->name = 'Adaías Magdiel';
$user->email = 'adaias@example.com';
$user->save();

// 5. Fetch the user
$found = User::find(1);
echo $found->name; // Adaías Magdiel
```

---

## 📦 Requirements

- **PHP** 8.1 or higher
- **PDO** extension enabled
- Compatible with **SQLite 3** and **MySQL/MariaDB**

---

## 🧰 Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require adaiasmagdiel/rubik-orm
```

---

## 🧭 Documentation Structure

| Section                               | Description                                          |
| ------------------------------------- | ---------------------------------------------------- |
| [Getting Started](getting-started.md) | Install Rubik and connect to your first database     |
| [Configuration](configuration.md)     | Driver setup, environment configuration, and options |
| [Models](models.md)                   | Define models and manage data records                |
| [Query Builder](queries.md)           | Build fluent SQL queries                             |
| [Relationships](relationships.md)     | Define associations between models                   |
| [SQL Raw Expressions](sql-raw.md)     | Use `SQL::raw()` safely                              |
| [API Reference](reference/model.md)   | Complete API documentation                           |

---

## ⚖️ License

Rubik ORM is open-source software licensed under the **GPLv3** License.
See the [LICENSE](https://github.com/adaiasmagdiel/rubik-orm/blob/main/LICENSE) file for details.
