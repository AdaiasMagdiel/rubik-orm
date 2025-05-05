# Rubik ORM

Rubik ORM is a lightweight and intuitive Object-Relational Mapping (ORM) library designed for **SQLite** and **MySQL/MariaDB** databases. Inspired by the simplicity and structure of a Rubik's Cube, Rubik provides a clean and efficient way to interact with databases using both **Active Record** and **Query Builder** patterns. It offers robust support for CRUD operations, relationships, and schema management while maintaining a minimal footprint.

## Table of Contents

- [Features](#features)
- [Use Cases](#use-cases)
- [Installation](#installation)
  - [Prerequisites](#prerequisites)
  - [Install via Composer](#install-via-composer)
- [Getting Started](#getting-started)
- [Basic Examples](#basic-examples)
  - [Creating a Record](#creating-a-record)
  - [Retrieving Records](#retrieving-records)
  - [Updating a Record](#updating-a-record)
  - [Deleting a Record](#deleting-a-record)
- [Advanced Examples](#advanced-examples)
  - [Query Builder](#query-builder)
  - [Relationships](#relationships)
  - [Bulk Insert](#bulk-insert)
- [SQLite and MySQL Examples](#sqlite-and-mysql-examples)
  - [SQLite Example](#sqlite-example)
  - [MySQL/MariaDB Example](#mysqlmariadb-example)
- [Models and Active Records](#models-and-active-records)
  - [Defining Models](#defining-models)
  - [Active Record Operations](#active-record-operations)
- [Query Builder](#query-builder-1)
- [Relationships](#relationships-1)
  - [BelongsTo](#belongsto)
  - [HasMany](#hasmany)
  - [Using Relationships](#using-relationships)
- [API Reference](#api-reference)
  - [Class: `FieldEnum`](#class-fieldenum)
  - [Class: `Rubik`](#class-rubik)
  - [Class: `Model` (Abstract)](#class-model-abstract)
  - [Class: `Query`](#class-query)
  - [Class: `Relationship`](#class-relationship)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Active Record Pattern**: Define models with schema mappings and perform CRUD operations directly on model instances.
- **Query Builder**: Construct complex SQL queries fluently with methods like `where`, `select`, `join`, `limit`, `whereIn` and `paginate`.
- **Database Support**: Optimized for SQLite and MySQL/MariaDB, with driver-specific configurations and foreign key support.
- **Relationships**: Supports `belongsTo` and `hasMany` relationships for easy data modeling.
- **Schema Management**: Define table schemas programmatically and create tables with custom field types (e.g., INTEGER, TEXT, BOOLEAN).
- **Lightweight**: Minimal dependencies, requiring only PHP 8.1+ and PDO.
- **Type Safety**: Uses enums (`FieldEnum`) for field types and strict typing for better code reliability.
- **Flexible Connections**: Singleton-like PDO connection management with support for custom configurations.

## Use Cases

Rubik ORM is ideal for:
- **Small to Medium Projects**: Perfect for applications needing a lightweight ORM without the overhead of larger frameworks like Laravel's Eloquent.
- **SQLite-Driven Applications**: Mobile apps, desktop tools, or embedded systems using SQLite databases.
- **MySQL/MariaDB Projects**: Web applications requiring relational database interactions with MySQL or MariaDB.
- **Rapid Prototyping**: Quickly set up database interactions with minimal configuration.
- **Educational Purposes**: Learn ORM concepts with a simple, transparent implementation.

## Installation

### Prerequisites
- PHP 8.1 or higher
- PDO extension enabled (included by default in most PHP installations)
- Composer (for dependency management)

### Install via Composer
Run the following command to install Rubik ORM:

```bash
composer require adaiasmagdiel/rubik
```

Alternatively, add the following to your `composer.json` and run `composer update`:

```json
{
    "require": {
        "adaiasmagdiel/rubik": "^1.0"
    }
}
```

## Getting Started

Follow these steps to start using Rubik ORM in your project:

1. **Set Up the Database Connection**:
   Configure and establish a connection using the `Rubik` class. For SQLite, specify a file path or use `:memory:` for an in-memory database. For MySQL/MariaDB, provide host, database, and credentials.

   ```php
   use AdaiasMagdiel\Rubik\Rubik;

   // SQLite connection
   Rubik::connect([
       'driver' => 'sqlite',
       'path' => 'path/to/database.sqlite'
   ]);

   // MySQL connection
   Rubik::connect([
       'driver' => 'mysql',
       'host' => 'localhost',
       'database' => 'myapp',
       'username' => 'user',
       'password' => 'password',
       'charset' => 'utf8mb4'
   ]);
   ```

2. **Define a Model**:
   Create a model by extending the `Model` class and defining the table schema in the `fields()` method.

   ```php
   use AdaiasMagdiel\Rubik\Model;

   class User extends Model
   {
       protected static string $table = 'users';

       protected static function fields(): array
       {
           return [
               'id' => self::Int(autoincrement: true, primaryKey: true),
               'name' => self::Text(notNull: true),
               'email' => self::Text(unique: true, notNull: true),
               'created_at' => self::DateTime(default: 'CURRENT_TIMESTAMP')
           ];
       }
   }
   ```

3. **Create the Table**:
   Use the `createTable` method to generate the database table based on the model's schema.

   ```php
   User::createTable(ifNotExists: true);
   ```

4. **Perform CRUD Operations**:
   Use Active Record methods to interact with the database.

   ```php
   // Create a new user
   $user = new User();
   $user->name = 'John Doe';
   $user->email = 'john@example.com';
   $user->save();

   // Retrieve a user
   $user = User::find(1);
   echo $user->name; // John Doe

   // Update a user
   $user->name = 'Jane Doe';
   $user->save();

   // Delete a user
   $user->delete();
   ```

## Basic Examples

### Creating a Record
```php
$user = new User();
$user->name = 'Alice Smith';
$user->email = 'alice@example.com';
$user->save(); // Inserts the record into the users table
```

### Retrieving Records
```php
// Find a user by ID
$user = User::find(1);

// Find all users
$users = User::all();

// Find a user by email
$user = User::findOneBy('email', 'alice@example.com');
```

### Updating a Record
```php
$user = User::find(1);
$user->name = 'Alice Johnson';
$user->save(); // Updates the record
```

### Deleting a Record
```php
$user = User::find(1);
$user->delete(); // Deletes the record
```

## Advanced Examples

### Query Builder
The Query Builder allows you to construct complex queries fluently.

```php
// Select specific fields with conditions
$users = User::query()
    ->select(['name', 'email'])
    ->where('created_at', '2023-01-01', '>')
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->all();

// Complex query with WHERE IN
$emails = ['alice@example.com', 'bob@example.com'];
$users = User::query()
    ->whereIn('email', $emails)
    ->all();

// Paginate results
$results = User::query()
    ->paginate(page: 1, perPage: 20);

// Paginate results with where clause
$results = User::query()
    ->where('created_at', '2023-01-01', '>')
    ->paginate(page: 1, perPage: 20);

// Update multiple records
User::query()
    ->where('created_at', '2023-01-01', '<')
    ->update(['name' => 'Archived User']);
```

### Relationships
Define relationships between models using `belongsTo` and `hasMany`.

```php
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Relationship;

class Post extends Model
{
    protected static string $table = 'posts';

    protected static function fields(): array
    {
        return [
            'id' => self::Int(autoincrement: true, primaryKey: true),
            'user_id' => self::Int(notNull: true),
            'title' => self::Text(notNull: true),
            'content' => self::Text()
        ];
    }

    public function user(): Relationship
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

class User extends Model
{
    // ... fields() as defined earlier

    public function posts(): Relationship
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}

// Create tables
User::createTable(ifNotExists: true);
Post::createTable(ifNotExists: true);

// Create a user and posts
$user = new User();
$user->name = 'Bob';
$user->email = 'bob@example.com';
$user->save();

$post1 = new Post();
$post1->user_id = $user->id;
$post1->title = 'First Post';
$post1->content = 'Hello, world!';
$post1->save();

$post2 = new Post();
$post2->user_id = $user->id;
$post2->title = 'Second Post';
$post2->content = 'Another post.';
$post2->save();

// Access relationships
$user = User::find(1);
$posts = $user->posts; // Array of Post instances
foreach ($posts as $post) {
    echo $post->title . "\n";
}

$post = Post::find(1);
$user = $post->user; // User instance
echo $user->name; // Bob
```

### Bulk Insert
Insert multiple records efficiently using `insertMany`.

```php
$users = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com']
];
User::insertMany($users);
```

## SQLite and MySQL Examples

### SQLite Example
Using an in-memory SQLite database for testing:

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Model;

Rubik::connect([
    'driver' => 'sqlite',
    'path' => ':memory:'
]);

// Define and create a model
class Product extends Model
{
    protected static string $table = 'products';

    protected static function fields(): array
    {
        return [
            'id' => self::Int(autoincrement: true, primaryKey: true),
            'name' => self::Text(notNull: true),
            'price' => self::Real(notNull: true),
            'is_active' => self::Boolean(default: true)
        ];
    }
}

Product::createTable();

// Insert a product
$product = new Product();
$product->name = 'Laptop';
$product->price = 999.99;
$product->is_active = true;
$product->save();

// Query products
$products = Product::query()
    ->where('price', 500, '>')
    ->all();
foreach ($products as $product) {
    echo "{$product->name}: \${$product->price}\n";
}
```

### MySQL/MariaDB Example
Using a MySQL database for a web application:

```php
use AdaiasMagdiel\Rubik\Rubik;

Rubik::connect([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'shop',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4'
]);

// Define and create a model
class Order extends Model
{
    protected static string $table = 'orders';

    protected static function fields(): array
    {
        return [
            'id' => self::Int(autoincrement: true, primaryKey: true),
            'user_id' => self::Int(notNull: true),
            'total' => self::Real(notNull: true),
            'created_at' => self::DateTime(default: 'CURRENT_TIMESTAMP')
        ];
    }
}

Order::createTable(ifNotExists: true);

// Insert an order
$order = new Order();
$order->user_id = 1;
$order->total = 149.99;
$order->save();

// Query orders
$orders = Order::query()
    ->where('total', 100, '>')
    ->orderBy('created_at', 'DESC')
    ->limit(5)
    ->all();
foreach ($orders as $order) {
    echo "Order #{$order->id}: \${$order->total}\n";
}
```

## Models and Active Records

### Defining Models
Models represent database tables and are defined by extending the `Model` class. The `fields()` method specifies the table schema using field types from `FieldEnum` (e.g., INTEGER, TEXT, REAL).

```php
class Book extends Model
{
    protected static string $table = 'books';

    protected static function fields(): array
    {
        return [
            'id' => self::Int(autoincrement: true, primaryKey: true),
            'title' => self::Text(notNull: true),
            'author' => self::Text(),
            'price' => self::Real(default: 0.0),
            'published' => self::Boolean(default: false)
        ];
    }
}
```

### Active Record Operations
The Active Record pattern allows you to manipulate records as objects:

- **Create**: Instantiate a model, set properties, and call `save()`.
- **Read**: Use `find`, `findOneBy`, `findAllBy`, or `all` to retrieve records.
- **Update**: Modify properties and call `save()` to update the record.
- **Delete**: Call `delete()` on a model instance.

Example:
```php
$book = new Book();
$book->title = 'PHP Essentials';
$book->author = 'Jane Doe';
$book->price = 29.99;
$book->published = true;
$book->save();

$book = Book::find(1);
$book->price = 34.99;
$book->save();

$book->delete();
```

## Query Builder

The Query Builder provides a fluent interface for constructing SQL queries. It supports:
- **SELECT Queries**: `select`, `where`, `whereIn`, `orderBy`, `limit`, `offset`.
- **JOIN Operations**: `join`, `leftJoin`, `rightJoin`.
- **UPDATE and DELETE**: `update`, `delete`, `exec`.
- **Aggregation**: `groupBy`, `having`.
- **Pagination**: `paginate`.

Example:
```php
$users = User::query()
    ->select(['id', 'name'])
    ->where('id', 5, '>')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->groupBy('users.id')
    ->having('COUNT(posts.id) > 0')
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->all();
```

Or paginate results:

```php
$res = User::query()
    ->paginate(page: 2, perPage: 10);

$users = $res["data"];
$currentPage = $res["current_page"];
$perPage = $res["per_page"];
$total = $res["total"];
$lastPage = $res["last_page"];
```

## Relationships

Rubik ORM supports `belongsTo` and `hasMany` relationships, allowing you to model one-to-one and one-to-many associations.

### BelongsTo
A model can belong to another model via a foreign key.

```php
class Comment extends Model
{
    protected static string $table = 'comments';

    protected static function fields(): array
    {
        return [
            'id' => self::Int(autoincrement: true, primaryKey: true),
            'post_id' => self::Int(notNull: true),
            'content' => self::Text(notNull: true)
        ];
    }

    public function post(): Relationship
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
```

### HasMany
A model can have multiple related models.

```php
class Post extends Model
{
    // ... fields() as defined earlier

    public function comments(): Relationship
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
}
```

### Using Relationships
Access related data dynamically:

```php
$post = Post::find(1);
$comments = $post->comments; // Array of Comment instances

$comment = Comment::find(1);
$post = $comment->post; // Post instance
```

## API Reference

### Class: `FieldEnum`

An enumeration defining SQLite and MySQL/MariaDB field data types.

- **Cases**:
  - `INTEGER`: Represents SQLite/MySQL INTEGER type (`string`).
  - `TEXT`: Represents SQLite/MySQL TEXT type (`string`).
  - `REAL`: Represents SQLite/MySQL REAL/FLOAT type (`string`).
  - `BLOB`: Represents SQLite/MySQL BLOB type (`string`).
  - `NUMERIC`: Represents SQLite/MySQL NUMERIC type (`string`).
  - `BOOLEAN`: Represents SQLite/MySQL BOOLEAN type, stored as 0 or 1 (`string`).
  - `DATETIME`: Represents SQLite/MySQL DATETIME type (`string`).

### Class: `Rubik`

Main entry point for managing database connections.

- `public static function connect(array $config): void`
- `public static function getConn(): PDO|null`
- `public static function disconnect(): void`
- `public static function isConnected(): bool`

### Class: `Model` (Abstract)

Abstract base class for database models, implementing Active Record pattern.

- `public function __set(string $key, mixed $value): void`
- `public function __get(string $key): mixed`
- `public static function query(): Query`
- `public function save(bool $ignore = false): bool`
- `public static function insertMany(array $records): bool`
- `public function update(): bool`
- `public function delete(): bool`
- `public static function all(array|string $fields = '*'): array`
- `public static function find(mixed $pk): ?static`
- `public static function findOneBy(string $key, mixed $value, string $op = '='): ?static`
- `public static function findAllBy(string $key, mixed $value, string $op = '='): array`
- `public static function paginate(int $page, int $perPage, array|string $fields = '*'): \stdClass`
- `public static function createTable(bool $ifNotExists = false): bool`
- `public function belongsTo(string $related, string $foreignKey): Relationship`
- `public function hasMany(string $related, string $foreignKey): Relationship`
- `public static function primaryKey(): string`
- `public static function getTableName(): string`
- `protected static function fields(): array`
- `protected static function getFieldString(array $field): string`
- `protected static function escapeDefaultValue(mixed $value): string`
- `public static function Int(bool $autoincrement = false, bool $primaryKey = false, bool $unique = false, bool $notNull = false, ?int $default = null): array`
- `public static function Text(bool $unique = false, bool $notNull = false, bool $primaryKey = false, ?string $default = null): array`
- `public static function Real(bool $unique = false, bool $notNull = false, bool $primaryKey = false, ?float $default = null): array`
- `public static function Blob(bool $unique = false, bool $notNull = false, mixed $default = null): array`
- `public static function Numeric(bool $unique = false, bool $notNull = false, bool $primaryKey = false, int|float|null $default = null): array`
- `public static function Boolean(bool $notNull = false, ?bool $default = null): array`
- `public static function DateTime(bool $notNull = false, ?string $default = null): array`

### Class: `Query`

Query builder for constructing and executing SQL queries.

- `public function setTable(string $table): self`
- `public function setModel(string $model): self`
- `public function select(string|array $fields = '*'): self`
- `public function where(string $key, mixed $value, string $op = '='): self`
- `public function orWhere(string $key, mixed $value, string $op = '='): self`
- `public function whereIn(string $key, array $values): self`
- `public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self`
- `public function leftJoin(string $table, string $first, string $operator, string $second): self`
- `public function rightJoin(string $table, string $first, string $operator, string $second): self`
- `public function orderBy(string $column, string $direction = 'ASC'): self`
- `public function groupBy(string|array $columns): self`
- `public function having(string $condition): self`
- `public function limit(int $limit): self`
- `public function offset(int $offset): self`
- `public function delete(): self`
- `public function update(array $data): bool`
- `public function all(): array`
- `public function first(): ?object`
- `public function exec(): bool`
- `public function paginate(int $page, int $perPage): array`
- `public function getSql(): string`

### Class: `Relationship`

Represents relationships between models (`belongsTo` and `hasMany`).

- `public function __construct(string $type, string $parentModel, string $relatedModel, string $foreignKey, ?object $parentInstance = null)`
- `public function getResults(): mixed`

## Contributing

Contributions are welcome! To contribute:
1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/your-feature`).
3. Commit your changes (`git commit -m 'Add your feature'`).
4. Push to the branch (`git push origin feature/your-feature`).
5. Open a Pull Request.

Please include tests and update the documentation as needed.

## License

Rubik ORM is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
See the [LICENSE](LICENSE) and [COPYRIGHT](COPYRIGHT) files for details.
