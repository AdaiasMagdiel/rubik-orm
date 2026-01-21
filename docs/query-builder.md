# Query Builder

The `Query` class allows you to construct complex SQL queries using a fluent, chainable interface. It handles all parameter binding automatically to prevent SQL Injection.

## Retrieving Data

### `all()` vs `get()`

- `all()`: Executes the query and returns an array of hydrated Model objects (or `stdClass` if no model is set).
- `get()`: Alias for `all()`, commonly used in Relationships.

### `first()`

Adds `LIMIT 1` and returns a single Model instance or `null`.

### `cursor()` (Memory Efficient)

Uses a PHP Generator to yield one model at a time. Essential for processing thousands of records without running out of RAM.

```php
foreach (User::query()->cursor() as $user) {
    // Only one User object exists in memory at a time
    process($user);
}
```

### `chunk(int $size, callable $cb)`

Retrieves records in "pages" of size `$size`.

```php
User::chunk(100, function(array $users) {
    // Process 100 users at a time
});
```

## Logic Clauses

### Where / OrWhere

- **Signature:** `where(col, value)` OR `where(col, operator, value)`
- **Signature:** `where(col, 'IS', null)` OR `where(col, 'IS NOT', null)`

```php
$q->where('votes', '>', 100)
  ->where('status', 'active') // Implies '='
  ->orWhere('is_vip', true);
```

### WhereIn

```php
$q->whereIn('id', [1, 5, 7]);
```

### WhereExists (Subqueries)

Rubik supports subqueries for existence checks.

```php
$orders = Order::query()->where('total', '>', 500);

// Select Users who have orders > 500
$users = User::whereExists($orders)->all();
```

## Pagination

The `paginate()` method simplifies frontend integration.

```php
$result = User::where('active', 1)->paginate(page: 2, perPage: 15);

// Structure of $result:
// [
//    'data' => [ ... objects ... ],
//    'total' => 150,
//    'per_page' => 15,
//    'current_page' => 2,
//    'last_page' => 10
// ]
```

## Aggregates

### `count()`

Returns the integer count of rows matching the criteria. It automatically modifies the `SELECT` clause to `COUNT(*)` temporarily.

```php
$count = User::where('age', '>', 18)->count();
```

### `exec()`

Returns `true` if _at least one_ row matches. Optimized to fetch only 1 row.

```php
if (User::where('email', 'exists@email.com')->exec()) {
    echo "Email taken!";
}
```
