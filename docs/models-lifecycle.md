# Models & Lifecycle

Rubik Models are the heart of your application. They handle data transport, persistence, and business logic events.

## Dirty State Tracking

Rubik employs an intelligent "Dirty Checking" mechanism.

1.  When a model is fetched (`find`, `all`), it is considered "clean".
2.  When you modify a property (`$user->name = 'New'`), the model marks that specific field as dirty in `$_dirty`.
3.  When `save()` or `update()` is called, **only the dirty fields** are included in the `UPDATE` SQL statement.

**Benefit:** This prevents overwriting data changed by other processes and reduces query size.

## The `save()` Method

The `save()` method is smart. It determines whether to `INSERT` or `UPDATE` based on the internal `$exists` boolean property.

```php
$user = new User();
$user->save(); // Performs INSERT, sets $exists = true

$user->name = "Changed";
$user->save(); // Performs UPDATE on ID
```

### Ignore Mode

You can pass `true` to `save()` to perform an "Insert Ignore".

- **MySQL:** `INSERT IGNORE INTO...`
- **SQLite:** `INSERT OR IGNORE INTO...`

```php
$user->save(ignore: true); // Skips error if Duplicate Key occurs
```

## Batch Operations

### `insertMany`

Inserts multiple arrays of raw data. This bypasses Model events and setters for performance.

```php
User::insertMany([
    ['name' => 'A', 'email' => 'a@a.com'],
    ['name' => 'B', 'email' => 'b@b.com'],
]);
```

!!! warning "ID Retrieval"
`insertMany` attempts to return the inserted IDs. However, due to limitations in **SQLite** and **MySQL < 8.0.22**, batch inserts do not reliably return _all_ generated Auto-Increment IDs.

## Lifecycle Hooks

Rubik fires protected methods during the model's lifecycle. Override these in your model to add logic (e.g., Hashing passwords, UUID generation).

| Hook           | Trigger Point                       |
| :------------- | :---------------------------------- |
| `beforeCreate` | Before an INSERT query.             |
| `afterCreate`  | After a successful INSERT.          |
| `beforeUpdate` | Before an UPDATE query.             |
| `afterUpdate`  | After a successful UPDATE.          |
| `beforeSave`   | Runs before both Create and Update. |
| `afterSave`    | Runs after both Create and Update.  |
| `beforeDelete` | Before DELETE query.                |
| `afterDelete`  | After a successful DELETE.          |

**Example: Auto-hashing Password**

```php
class User extends Model {
    protected function beforeSave(): void {
        if (isset($this->_dirty['password'])) {
            $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        }
    }
}
```

## Serialization

Models implement `JsonSerializable`. When you `json_encode($model)`, it automatically calls `toArray()`, which returns the internal `$_data` array.

```php
echo json_encode($user);
// {"id": 1, "name": "John", ...}
```
