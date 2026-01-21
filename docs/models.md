# Models & Schema

Rubik models represent database tables. They encapsulate logic for data access, validation (via schema), and business rules.

## Defining a Model

To create a model, extend the `AdaiasMagdiel\Rubik\Model` class. You must implement the `fields()` method to define the table schema.

```php
<?php
namespace App\Model;

use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Column;
use AdaiasMagdiel\Rubik\SQL;

class User extends Model
{
    // Optional: Explicitly define table name
    protected static string $table = 'users';

    protected static function fields(): array
    {
        return [
            'id' => Column::Integer(primaryKey: true, autoIncrement: true),

            'username' => Column::Varchar(length: 50, unique: true, notNull: true),

            'email' => Column::Varchar(length: 100, unique: true),

            'is_active' => Column::Boolean(default: true),

            'created_at' => Column::DateTime(
                default: SQL::raw('CURRENT_TIMESTAMP')
            ),

            // Defining a relationship column
            'role_id' => Column::Integer(
                foreignKey: Column::ForeignKey('id', 'roles', onDelete: 'CASCADE')
            )
        ];
    }
}
```

## Schema Management

Rubik allows you to create or drop tables based on the model definition. This is useful for migrations or prototyping.

```php
// Create the table if it doesn't exist
User::createTable(ifNotExists: true);

// Drop the table
User::dropTable(ifExists: true);

// Truncate (empty) the table
User::truncateTable();
```

## CRUD Operations

### Creating

```php
$user = new User();
$user->username = "rubik_dev";
$user->email = "dev@example.com";
$user->save(); // Returns bool
```

### Reading

```php
// Find by Primary Key
$user = User::find(1);

// Get first matching record
$user = User::where('username', 'rubik_dev')->first();

// Get all records
$users = User::all();

// Pagination
$page = User::paginate(page: 1, perPage: 15);
// returns ['data' => [...], 'total' => 100, ...]
```

### Updating

Only fields that have changed ("dirty" fields) are sent to the database.

```php
$user = User::find(1);
$user->email = "new_email@example.com";
$user->save();
```

### Deleting

```php
$user = User::find(1);
$user->delete();
```
