# Getting Started

## Installation

Rubik is installed via Composer.

```bash
composer require adaiasmagdiel/rubik
```

## Connection Management

Rubik uses a **Singleton** pattern for database connections. This means your application currently supports **one active database connection** at a time.

### The `Rubik::connect` Method

You must call `connect` before your application attempts any database operations.

```php
public static function connect(
    Driver $driver,
    string $username = '',
    string $password = '',
    string $database = '',
    int $port = 3306,
    string $host = 'localhost',
    string $charset = 'utf8mb4',
    string $path = ":memory:",
    array $options = []
): void
```

### Driver: SQLite

For SQLite, the `path` argument is critical. Rubik automatically executes `PRAGMA foreign_keys = ON;` for SQLite connections to ensure referential integrity.

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

Rubik::connect(
    driver: Driver::SQLITE,
    path: __DIR__ . '/database.sqlite', // or ":memory:"
    options: [
        PDO::ATTR_TIMEOUT => 5 // Optional PDO settings
    ]
);
```

### Driver: MySQL / MariaDB

Rubik handles connection strings automatically.

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

Rubik::connect(
    driver: Driver::MYSQL,
    host: '127.0.0.1',
    port: 3306,
    database: 'production_db',
    username: 'admin',
    password: 'secure_password',
    charset: 'utf8mb4'
);
```

!!! warning "Strict Mode"
Rubik sets `PDO::ATTR_ERRMODE` to `PDO::ERRMODE_EXCEPTION` by default. Any SQL error will throw a `PDOException`. It also sets `ATTR_EMULATE_PREPARES` to `false` for security.

## Disconnecting

If you need to close the connection or switch databases during testing:

```php
Rubik::disconnect();
```

## Global State Helper

You can check if a connection is active or access the raw PDO instance if you need to perform operations outside the ORM.

```php
if (Rubik::isConnected()) {
    $pdo = Rubik::getConn();
    // Do raw PDO stuff...
}
```
