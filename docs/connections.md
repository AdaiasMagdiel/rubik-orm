# Database Connections

Rubik ORM uses PHP’s **PDO** engine to manage database connections in a consistent and driver-aware way.  
It simplifies the connection lifecycle — from establishing and checking connections to managing multiple databases for different environments.

---

## 🔌 Overview

Rubik maintains a **single global PDO connection** internally, which all models and query builders share.  
This means you don’t need to manually pass the connection object — once you call:

```php
Rubik::connect(...)
```

All subsequent ORM operations (queries, models, schema builders) will automatically use the same connection.

---

## ⚙️ The Connection Lifecycle

1. **Connect** using `Rubik::connect()`
2. **Execute queries** using models or the query builder
3. **Check connection** with `Rubik::isConnected()`
4. **Disconnect** with `Rubik::disconnect()`

Example:

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

// 1. Connect
Rubik::connect(Driver::SQLITE, path: ':memory:');

// 2. Perform database actions
User::createTable();
User::query()->where('id', 1)->first();

// 3. Check connection
if (Rubik::isConnected()) {
    echo "Connection is active.";
}

// 4. Disconnect
Rubik::disconnect();
```

---

## 🧩 The Rubik Connection API

Rubik provides several static methods to manage and inspect the current connection.

| Method                      | Description                                             | Return Type |
| --------------------------- | ------------------------------------------------------- | ----------- |
| `Rubik::connect(...)`       | Opens a PDO connection and sets the current driver      | `void`      |
| `Rubik::getConn()`          | Returns the current PDO instance                        | `PDO`       |
| `Rubik::isConnected()`      | Checks if a connection is currently active              | `bool`      |
| `Rubik::getDriver()`        | Returns the current driver enum                         | `Driver`    |
| `Rubik::disconnect()`       | Closes the active connection                            | `void`      |
| `Rubik::setDriver($driver)` | Manually sets a driver without connecting (for testing) | `void`      |

---

## 🧠 Inspecting the Current Connection

You can retrieve the underlying PDO object to perform raw SQL queries or low-level operations.

```php
$pdo = Rubik::getConn();

$stmt = $pdo->query('SELECT sqlite_version()');
echo $stmt->fetchColumn();
```

Rubik’s PDO instance is configured with sensible defaults:

```php
PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
PDO::ATTR_EMULATE_PREPARES   => false
PDO::ATTR_STRINGIFY_FETCHES  => false
```

These ensure robust error handling and consistent typing.

---

## ⚙️ Using Multiple Databases

Rubik ORM is designed to maintain **one active connection** at a time.
If you need to interact with multiple databases, you can **swap connections on the fly**:

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

// Connect to SQLite
Rubik::connect(Driver::SQLITE, path: 'database.sqlite');

// Run queries on SQLite
User::createTable();

// Switch to MySQL
Rubik::connect(
    Driver::MYSQL,
    username: 'root',
    password: '',
    database: 'rubik_production'
);

// Now all models and queries use MySQL
User::createTable();
```

### 💡 Tip

For multi-database applications, wrap your connection logic in a custom class or helper function:

```php
function connectTo(string $env): void {
    match ($env) {
        'testing' => Rubik::connect(Driver::SQLITE, path: ':memory:'),
        'local'   => Rubik::connect(Driver::SQLITE, path: __DIR__ . '/../db/local.sqlite'),
        'prod'    => Rubik::connect(Driver::MYSQL, username: 'root', password: 'secret', database: 'prod_db'),
        default   => throw new RuntimeException("Unknown environment: {$env}")
    };
}
```

---

## 🧮 Connection Persistence

Each call to `Rubik::connect()` creates a new PDO connection.

If you need **persistent connections**, you can enable it via the `$options` array:

```php
Rubik::connect(
    driver: Driver::MYSQL,
    username: 'root',
    password: '',
    database: 'rubik',
    options: [
        PDO::ATTR_PERSISTENT => true
    ]
);
```

> ⚠️ Use persistent connections with caution in environments where database state may persist across requests (e.g. long-running PHP daemons).

---

## 🧾 Custom PDO Options

Rubik lets you fully control PDO behavior via the `$options` parameter of `connect()`:

```php
Rubik::connect(
    driver: Driver::MYSQL,
    username: 'root',
    password: '',
    database: 'rubik',
    options: [
        PDO::ATTR_TIMEOUT => 5,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'"
    ]
);
```

The provided array merges with Rubik’s defaults, ensuring safe operation.

---

## 🧪 Testing Database Connections

You can quickly verify connectivity using PHP exceptions:

```php
try {
    Rubik::connect(Driver::MYSQL, username: 'root', password: 'wrong');
} catch (RuntimeException $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

For SQLite, most issues occur due to **invalid file paths or permissions**:

```php
Rubik::connect(Driver::SQLITE, path: '/invalid/path/data.sqlite');
// Throws: RuntimeException("Failed to connect to database: unable to open database file")
```

---

## 🧭 Connection States and Errors

| Error Message                   | Meaning                                        | Typical Fix                                  |
| ------------------------------- | ---------------------------------------------- | -------------------------------------------- |
| `No active database connection` | You’re trying to use a model before connecting | Call `Rubik::connect()` first                |
| `Unsupported database driver`   | You passed an invalid `Driver` enum            | Use `Driver::MYSQL` or `Driver::SQLITE` only |
| `Failed to connect to database` | PDO connection error                           | Check credentials or path                    |
| `SQLSTATE[HY000]` errors        | SQL syntax or engine issues                    | Validate your queries and schema             |

Rubik wraps all PDO exceptions in `RuntimeException` to ensure consistent error reporting.

---

## 🧩 Connection Validation Example

You can easily build an environment checker to confirm that Rubik ORM is ready:

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

try {
    Rubik::connect(Driver::MYSQL, username: 'root', password: '', database: 'rubik_test');
    echo "✅ Connected to MySQL successfully.\n";
    echo "Driver: " . Rubik::getDriver()->value;
} catch (RuntimeException $e) {
    echo "❌ Database connection failed: " . $e->getMessage();
}
```

---

## 🧱 Example: Switching Between Memory and File-Based SQLite

```php
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

// Use an in-memory database for testing
Rubik::connect(Driver::SQLITE, path: ':memory:');
User::createTable();

// Populate test data
User::insertMany([
    ['name' => 'Alice'],
    ['name' => 'Bob']
]);

// Switch to file-based SQLite
Rubik::connect(Driver::SQLITE, path: __DIR__ . '/database.sqlite');

// Persisted data can be managed independently
User::createTable(ifNotExists: true);
```

---

## 🧰 Example: Manual PDO Query Execution

If you need direct access to SQL (e.g., migrations or analytics), you can always fall back to raw PDO:

```php
$pdo = Rubik::getConn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
$stmt->execute();

$count = $stmt->fetchColumn();
echo "Total users: {$count}";
```

Rubik is intentionally designed to **coexist with direct PDO usage** — no hidden magic, no blocking abstractions.

---

## 🧾 Summary

✅ **Key takeaways:**

- Rubik maintains a **single shared PDO connection**
- Always call `Rubik::connect()` before using models or queries
- Switch databases by reconnecting with different drivers
- Manage persistence, options, and environments flexibly
- Access `Rubik::getConn()` for full PDO control
- Use exceptions to handle connection failures cleanly

---

## 🧭 Next Steps

Continue with:

- [Models](./models.md) — defining tables, fields, and relationships
- [Query Builder](./queries.md) — building advanced, fluent SQL queries
