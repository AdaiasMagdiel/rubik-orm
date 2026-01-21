# Advanced Topics

## Transactions

Rubik wraps PDO transactions. You must ensure your tables support transactions (e.g., InnoDB for MySQL).

### Helper Method (Recommended)

The `transaction` method automatically commits on success and rolls back if _any_ `Throwable` is caught.

```php
try {
    Rubik::transaction(function() {
        $u = new User();
        $u->save();

        if (somethingWrong()) {
            throw new Exception("Rollback!");
        }
    });
} catch (Exception $e) {
    // Transaction already rolled back here
}
```

### Manual Control

```php
Rubik::beginTransaction();
// ... logic
Rubik::commit();
// or
Rubik::rollBack();
```

## Security: The `SQL` Value Object

Rubik automatically escapes all inputs passed to `where`, `insert`, and `update`. However, sometimes you need to pass raw SQL (e.g., database functions).

To do this securely, use the `AdaiasMagdiel\Rubik\SQL` class. Rubik detects instances of this class and injects the string **verbatim**.

```php
use AdaiasMagdiel\Rubik\SQL;

// BAD: Potentially unsafe if $input is dirty
$q->update(['updated_at' => "NOW()"]); // Will define string "NOW()"

// GOOD:
$q->update(['updated_at' => SQL::raw('NOW()')]); // Will execute SQL function
```

!!! danger "Security Warning"
**NEVER** pass user-supplied data into `SQL::raw()`.

    Bad: `SQL::raw("dATEDIFF(now(), '$userInput')")` -> **SQL Injection**.
    Good: `where('date', '<', $userInput)` -> **Safe (Parameterized)**.

## Identifiers vs Values

Rubik internally distinguishes between:

1.  **Identifiers:** Table names and Column names. These are sanitized via `quoteIdentifier` (adds backticks `` ` `` or double quotes `"`).
2.  **Values:** Data content. These are parameterized as `:placeholder`.

## Scopes (Magic Methods)

You can define reusable query logic in your Model using the `scope` prefix.

**Definition:**

```php
class User extends Model {
    public function scopeActive(Query $query) {
        $query->where('status', 'active');
    }
}
```

**Usage:**

```php
// Call it without the 'scope' prefix
$users = User::active()->orderBy('id')->all();
```

## Handling Database Differences

Rubik attempts to abstract differences, but some leak through:

1.  **Auto-Increment:** SQLite handles standard `INTEGER PRIMARY KEY` as auto-increment. MySQL requires the explicit `AUTO_INCREMENT` flag. Rubik's `SchemaTrait` handles this generation logic.
2.  **String vs Text:** In SQLite, `VARCHAR` is mapped to `TEXT`. In MySQL, strict lengths are enforced.
3.  **JSON:** MySQL has a native `JSON` type. SQLite stores it as `TEXT`. Rubik validates JSON validity in PHP before saving to ensure consistency.
