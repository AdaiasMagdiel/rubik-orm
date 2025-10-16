# 🧩 Rubik ORM Core

The `Rubik` class is the **central connection manager** of the ORM.  
It handles all **PDO connections**, driver setup, and configuration for SQLite and MySQL.

---

## ⚙️ Overview

Rubik acts as a static gateway between your PHP application and the database.  
It maintains a single PDO connection instance and exposes utilities for:

- Connecting and disconnecting from the database
- Detecting which driver is active (`MySQL` or `SQLite`)
- Accessing the PDO instance directly when needed
- Managing driver-specific behavior (e.g. `PRAGMA foreign_keys` for SQLite)

---

## 🧱 Importing

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;
```

---

## 🔌 Connecting

Use `Rubik::connect()` to establish a database connection.

### Example — SQLite

```php
Rubik::connect(
    driver: Driver::SQLITE,
    path: __DIR__ . '/database.sqlite'
);
```

> 💡 Automatically enables `PRAGMA foreign_keys = ON`.

### Example — MySQL

```php
Rubik::connect(
    driver: Driver::MYSQL,
    host: 'localhost',
    port: 3306,
    database: 'rubik_db',
    username: 'root',
    password: 'secret'
);
```

> Uses secure defaults: `utf8mb4`, `PDO::ERRMODE_EXCEPTION`, and no emulated prepares.

---

## 🧩 API Reference

### **Rubik::connect()**

```php
Rubik::connect(
    Driver $driver,
    string $username = '',
    string $password = '',
    string $database = '',
    int $port = 3306,
    string $host = 'localhost',
    string $charset = 'utf8mb4',
    string $path = ':memory:',
    array $options = []
): void
```

Connects to a database using the specified driver and configuration.

| Parameter  | Type     | Description                                                |
| ---------- | -------- | ---------------------------------------------------------- |
| `driver`   | `Driver` | The database driver (`Driver::SQLITE` or `Driver::MYSQL`). |
| `username` | `string` | Database username (for MySQL).                             |
| `password` | `string` | Database password (for MySQL).                             |
| `database` | `string` | Database name (for MySQL).                                 |
| `port`     | `int`    | Port number (default: 3306).                               |
| `host`     | `string` | Host address (default: localhost).                         |
| `charset`  | `string` | Character set (default: utf8mb4).                          |
| `path`     | `string` | Path to SQLite database file (`:memory:` by default).      |
| `options`  | `array`  | Additional PDO options.                                    |

Throws `RuntimeException` if connection fails.

---

### **Rubik::getConn()**

```php
public static function getConn(): PDO
```

Returns the active `PDO` connection.
Throws a `RuntimeException` if not connected.

```php
$pdo = Rubik::getConn();
$pdo->exec("SELECT 1");
```

---

### **Rubik::disconnect()**

```php
public static function disconnect(): void
```

Closes the current database connection and resets the driver.

```php
Rubik::disconnect();
```

---

### **Rubik::isConnected()**

```php
public static function isConnected(): bool
```

Checks whether a database connection is currently active.

```php
if (Rubik::isConnected()) {
    echo "Connected!";
}
```

---

### **Rubik::getDriver()**

```php
public static function getDriver(): Driver
```

Retrieves the active database driver.

```php
if (Rubik::getDriver() === Driver::SQLITE) {
    echo "Running on SQLite";
}
```

---

### **Rubik::setDriver()**

```php
public static function setDriver(Driver $driver): void
```

Forcefully sets the active driver manually.

> ⚠️ Intended for internal use and testing only.

Example:

```php
Rubik::setDriver(Driver::MYSQL);
```

---

## ⚙️ DSN Resolution

Rubik automatically builds the DSN string for the PDO connection:

| Driver     | Example DSN                                                      |
| ---------- | ---------------------------------------------------------------- |
| **SQLite** | `sqlite:/path/to/database.sqlite`                                |
| **MySQL**  | `mysql:host=localhost;port=3306;dbname=rubik_db;charset=utf8mb4` |

> You rarely need to call this directly — it’s handled internally by `connect()`.

---

## 🧰 Default PDO Options

Rubik sets sane and secure defaults:

| Option                         | Value                    |
| ------------------------------ | ------------------------ |
| `PDO::ATTR_ERRMODE`            | `PDO::ERRMODE_EXCEPTION` |
| `PDO::ATTR_DEFAULT_FETCH_MODE` | `PDO::FETCH_ASSOC`       |
| `PDO::ATTR_EMULATE_PREPARES`   | `false`                  |
| `PDO::ATTR_STRINGIFY_FETCHES`  | `false`                  |

---

## 🧾 Example — Complete Connection Setup

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

try {
    Rubik::connect(
        driver: Driver::MYSQL,
        host: '127.0.0.1',
        database: 'rubik_orm',
        username: 'root',
        password: '123456'
    );

    echo "Connected successfully via " . Rubik::getDriver()->value;
} catch (RuntimeException $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

---

## 📘 Related

- [Column](./column.md) — Defines table columns and constraints
- [Model](./model.md) — Base class for ORM models
- [Query](./query.md) — Fluent query builder
- [SQL](./sql.md) — Raw SQL expressions helper
