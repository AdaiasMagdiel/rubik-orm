# Getting Started

Welcome to **Rubik ORM** — a fast, lightweight, and driver-aware ORM for PHP.  
This guide will walk you through installation, configuration, and your first working model.

---

## 🧰 Installation

You can install Rubik ORM using **Composer**:

```bash
composer require adaiasmagdiel/rubik-orm
```

Ensure that you have:

- **PHP ≥ 8.1**
- The **PDO** extension enabled
- One of the supported database drivers:

  - `pdo_sqlite`
  - `pdo_mysql`

---

## ⚙️ Connecting to a Database

Before interacting with models, Rubik must be connected to a database using the static `Rubik::connect()` method.

### Example: SQLite (Recommended for Testing)

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

Rubik::connect(
    driver: Driver::SQLITE,
    path: ':memory:' // or '/path/to/database.sqlite'
);
```

### Example: MySQL / MariaDB

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

Rubik::connect(
    driver: Driver::MYSQL,
    username: 'root',
    password: 'secret',
    database: 'rubik_demo',
    host: '127.0.0.1',
    port: 3306
);
```

Rubik automatically adapts column types and SQL syntax depending on the active driver.

---

## 🧱 Creating a Model

Models represent database tables.
Each model must extend the `Rubik\Model` class and define two methods:

- `protected static string $table` — the table name
- `protected static function fields(): array` — the column schema

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

## 🏗️ Creating Tables

Once your model is defined, you can generate its corresponding table:

```php
User::createTable(ifNotExists: true);
```

To reset or remove it:

```php
User::truncateTable(); // removes all rows
User::dropTable(ifExists: true); // drops the table
```

---

## 💾 Inserting Records

Rubik models work like simple data containers:

```php
$user = new User();
$user->name = 'Adaías Magdiel';
$user->email = 'adaias@example.com';
$user->save();
```

---

## 🔍 Querying Data

Use static methods or the `query()` builder for full control.

```php
// Find by primary key
$user = User::find(1);

// Get all users
$users = User::all();

// Fluent query builder
$filtered = User::query()
    ->where('email', 'LIKE', '%example.com%')
    ->orderBy('id', 'DESC')
    ->limit(5)
    ->all();
```

---

## 🔄 Updating and Deleting

```php
// Update
$user = User::find(1);
$user->name = 'Updated Name';
$user->save();

// Delete
$user->delete();
```

---

## 🤝 Relationships

Rubik supports standard ORM relationships:

- `belongsTo`
- `hasOne`
- `hasMany`
- `belongsToMany`

Example:

```php
class Post extends Model {
    protected static function relationships(): array {
        return [
            'author' => [
                'type' => 'belongsTo',
                'related' => User::class,
                'foreignKey' => 'user_id',
                'ownerKey' => 'id',
            ],
        ];
    }
}

$post = Post::find(1);
echo $post->author->name;
```

---

## 🧪 Testing with SQLite Memory Databases

Rubik ORM was designed with testing in mind.

You can use an **in-memory SQLite** database to run isolated unit tests quickly:

```php
Rubik::connect(Driver::SQLITE, path: ':memory:');
User::createTable();

// Run tests freely
```

This makes it easy to test models, queries, and relationships without touching a real database.

---

## 🧭 Next Steps

Continue exploring Rubik ORM:

- [Configuration](./configuration.md) — customize your connection setup
- [Models](./models.md) — learn about field definitions, casting, and serialization
- [Query Builder](./queries.md) — advanced querying with joins and pagination
- [Relationships](./relationships.md) — define associations between models

---

## 💡 Tip

> If you’re coming from **Laravel’s Eloquent**, you’ll feel at home.
> Rubik offers similar patterns with a lighter, more explicit core — perfect for APIs, CLI tools, and microservices.

---

## 🧾 License

Rubik ORM is licensed under **GPLv3**.
You are free to use, modify, and distribute it under the same license terms.
