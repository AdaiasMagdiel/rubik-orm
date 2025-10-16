# Configuration

Rubik ORM is built on top of PHP’s **PDO** extension and provides a unified configuration interface through the static method:

```php
Rubik::connect()
```

This page explains how to configure and manage database connections for **SQLite** and **MySQL/MariaDB**, including available parameters, environment setup, and best practices.

---

## ⚙️ Basic Connection

All connections are created via:

```php
Rubik::connect(
    driver: Driver::SQLITE | Driver::MYSQL,
    username: string = '',
    password: string = '',
    database: string = '',
    port: int = 3306,
    host: string = 'localhost',
    charset: string = 'utf8mb4',
    path: string = ':memory:',
    options: array = []
): void
```

You can call this method **once per request** — Rubik manages a static internal connection that all models and queries share.

---

## 🧩 Supported Drivers

| Driver Enum      | Description                             | Example                                                                                 |
| ---------------- | --------------------------------------- | --------------------------------------------------------------------------------------- |
| `Driver::SQLITE` | Local file or in-memory SQLite database | `Rubik::connect(Driver::SQLITE, path: ':memory:');`                                     |
| `Driver::MYSQL`  | MySQL or MariaDB server connection      | `Rubik::connect(Driver::MYSQL, username: 'root', password: 'secret', database: 'app');` |

Rubik automatically adapts:

- SQL type definitions
- Column syntax (`AUTO_INCREMENT`, `AUTOINCREMENT`, etc.)
- Table creation and default value handling

---

## 🗄️ SQLite Configuration

SQLite is the simplest option for local development and testing.

### Example

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

Rubik::connect(
    driver: Driver::SQLITE,
    path: ':memory:' // or '/absolute/path/to/database.sqlite'
);
```

### Notes

- Use `:memory:` for **ephemeral test databases**.
- SQLite automatically creates the file if it does not exist.
- Rubik automatically enables **foreign key constraints** via `PRAGMA foreign_keys = ON;`.

---

## 🐬 MySQL / MariaDB Configuration

For production and network-based databases, use the MySQL driver.

### Example

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

Rubik::connect(
    driver: Driver::MYSQL,
    username: 'root',
    password: 'secret',
    database: 'rubik_app',
    host: '127.0.0.1',
    port: 3306,
    charset: 'utf8mb4'
);
```

### Parameters Reference

| Parameter  | Type          | Default       | Description                         |
| ---------- | ------------- | ------------- | ----------------------------------- |
| `driver`   | `Driver` enum | —             | `Driver::MYSQL` or `Driver::SQLITE` |
| `username` | `string`      | `''`          | Database username                   |
| `password` | `string`      | `''`          | Database password                   |
| `database` | `string`      | `''`          | Database name                       |
| `port`     | `int`         | `3306`        | Connection port                     |
| `host`     | `string`      | `'localhost'` | Database host                       |
| `charset`  | `string`      | `'utf8mb4'`   | Connection charset                  |
| `path`     | `string`      | `':memory:'`  | SQLite path only                    |
| `options`  | `array`       | `[]`          | Extra PDO options                   |

---

## ⚙️ Advanced PDO Options

Rubik merges your custom `$options` array with its internal defaults:

```php
[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
]
```

You can override or extend them as needed:

```php
Rubik::connect(
    driver: Driver::MYSQL,
    username: 'root',
    password: '',
    database: 'mydb',
    options: [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_PERSISTENT => true,
    ]
);
```

---

## 🧠 Checking Connection Status

Rubik offers helper methods to inspect or manage the current database connection.

```php
use AdaiasMagdiel\Rubik\Rubik;

// Check if a connection is active
if (Rubik::isConnected()) {
    echo "Connected!";
}

// Get the underlying PDO instance
$pdo = Rubik::getConn();

// Get the active driver (Driver enum)
$driver = Rubik::getDriver();

// Disconnect manually
Rubik::disconnect();
```

---

## ⚡ Forcing a Driver (Testing Only)

You can force a driver without opening a connection — useful for testing type behavior or SQL generation:

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

Rubik::setDriver(Driver::MYSQL);
```

⚠️ **Note:**
`setDriver()` does _not_ create a real PDO connection — it only overrides the internal driver used for SQL dialect logic.

---

## 🧱 Example: Multiple Environments

A common approach is to load connection settings from `.env` files or environment variables:

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

$env = getenv('APP_ENV') ?: 'development';

if ($env === 'production') {
    Rubik::connect(
        driver: Driver::MYSQL,
        username: getenv('DB_USER'),
        password: getenv('DB_PASS'),
        database: getenv('DB_NAME'),
        host: getenv('DB_HOST') ?: '127.0.0.1'
    );
} else {
    Rubik::connect(Driver::SQLITE, path: __DIR__ . '/../database/dev.sqlite');
}
```

This allows Rubik to automatically select the right database configuration depending on the runtime environment.

---

## 🧩 Example: In-Memory SQLite for Testing

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

Rubik::connect(Driver::SQLITE, path: ':memory:');

// Prepare schema for tests
User::createTable();
Post::createTable();
```

Perfect for **unit testing** and **continuous integration** setups.

---

## 🧰 Common Connection Errors

| Error                                           | Possible Cause                                   | Fix                                                      |
| ----------------------------------------------- | ------------------------------------------------ | -------------------------------------------------------- |
| `No active database connection`                 | `Rubik::connect()` was never called              | Ensure connection is established before any model action |
| `Unsupported database driver`                   | Using a non-implemented driver (e.g. PostgreSQL) | Currently only `MYSQL` and `SQLITE` are supported        |
| `SQLSTATE[HY000]: unable to open database file` | Invalid SQLite path                              | Use an absolute path or ensure directory permissions     |
| `Access denied for user`                        | Wrong credentials for MySQL                      | Check username/password or host in your configuration    |

---

## 🧾 Summary

Rubik ORM abstracts all connection complexity while keeping full control over the underlying PDO layer.

✅ **Key takeaways:**

- Use `Rubik::connect()` before interacting with any model or query.
- `Driver::SQLITE` is ideal for local or test setups.
- `Driver::MYSQL` is ideal for production environments.
- Rubik automatically manages and caches a single global connection.
- You can inspect, override, or close it at any time.

---

## 🧭 Next Steps

Continue with:

- [Database Connections](./connections.md) — deeper insight into connection management
- [Models](./models.md) — defining and mapping your data models
