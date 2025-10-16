# Relationships

Rubik ORM supports defining **relationships between models** using a clean, declarative syntax.  
Relationships are lazy-loaded on demand — meaning they are only queried when first accessed — and then **cached** for reuse.

Supported relationship types:

- `belongsTo` — a model references another one (foreign key on current table)
- `hasOne` — a model owns a single related record
- `hasMany` — a model owns multiple related records
- `belongsToMany` — many-to-many relationship through a pivot table

---

## ⚙️ Defining Relationships

Each model can define its relationships by overriding the static `relationships()` method:

```php
use App\Models\User;
use App\Models\Post;

class Post extends Model
{
    protected static string $table = 'posts';

    protected static function fields(): array
    {
        return [
            'id' => Column::Integer(primaryKey: true, autoincrement: true),
            'user_id' => Column::Integer(
                notNull: true,
                foreignKey: Column::ForeignKey('id', 'users', 'CASCADE', 'CASCADE')
            ),
            'title' => Column::Varchar(length: 255, notNull: true),
            'body' => Column::Text(),
        ];
    }

    protected static function relationships(): array
    {
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
```

> 💡 Rubik automatically infers most keys when you follow naming conventions
> (`user_id`, `post_id`, etc.), but you can override them explicitly.

---

## 🔁 belongsTo

Defines a **reverse** one-to-one or many-to-one relationship
(current model has a foreign key pointing to another model).

```php
$post = Post::find(1);
$user = $post->author; // Lazy-loaded User instance
```

### SQL generated

```sql
SELECT * FROM users WHERE id = :user_id LIMIT 1;
```

### Example

```php
class User extends Model
{
    protected static string $table = 'users';
}

class Post extends Model
{
    protected static string $table = 'posts';

    protected static function relationships(): array
    {
        return [
            'author' => [
                'type' => 'belongsTo',
                'related' => User::class,
                'foreignKey' => 'user_id',
            ],
        ];
    }
}
```

---

## 🧩 hasOne

Defines a **one-to-one** relationship (the current model owns exactly one related record).

```php
$user = User::find(1);
$profile = $user->profile;
```

### SQL generated

```sql
SELECT * FROM profiles WHERE user_id = :user_id LIMIT 1;
```

### Example

```php
class Profile extends Model
{
    protected static string $table = 'profiles';
}

class User extends Model
{
    protected static string $table = 'users';

    protected static function relationships(): array
    {
        return [
            'profile' => [
                'type' => 'hasOne',
                'related' => Profile::class,
                'foreignKey' => 'user_id',
                'localKey' => 'id',
            ],
        ];
    }
}
```

---

## 🧮 hasMany

Defines a **one-to-many** relationship (the current model has multiple related records).

```php
$user = User::find(1);
$posts = $user->posts;
```

### SQL generated

```sql
SELECT * FROM posts WHERE user_id = :id;
```

### Example

```php
class User extends Model
{
    protected static string $table = 'users';

    protected static function relationships(): array
    {
        return [
            'posts' => [
                'type' => 'hasMany',
                'related' => Post::class,
                'foreignKey' => 'user_id',
                'localKey' => 'id',
            ],
        ];
    }
}
```

> ⚡ The result of a `hasMany` relationship is always an **array of models**.

---

## 🔗 belongsToMany

Defines a **many-to-many** relationship using a pivot table.

```php
$post = Post::find(1);
$tags = $post->tags;
```

### SQL generated

```sql
SELECT tags.*
FROM tags
INNER JOIN post_tag ON post_tag.tag_id = tags.id
WHERE post_tag.post_id = :post_id;
```

### Example

```php
class Tag extends Model
{
    protected static string $table = 'tags';
}

class Post extends Model
{
    protected static string $table = 'posts';

    protected static function relationships(): array
    {
        return [
            'tags' => [
                'type' => 'belongsToMany',
                'related' => Tag::class,
                'pivotTable' => 'post_tag',
                'foreignKey' => 'post_id',
                'relatedKey' => 'tag_id',
                'localKey' => 'id',
                'relatedOwnerKey' => 'id',
            ],
        ];
    }
}
```

---

## 🧠 Lazy Loading and Caching

When you access a relationship property (like `$user->posts`), Rubik executes the relationship query **only once** per model instance.

Subsequent accesses reuse the cached results:

```php
$user = User::find(1);

// Executes one query
$posts = $user->posts;

// Uses cached data (no new query)
$postsAgain = $user->posts;
```

This improves performance without requiring eager loading.

---

## 🚀 Combining with Query Builder

Relationships return **Query** instances under the hood.
You can modify them like any other builder before executing:

```php
$user = User::find(1);

// Filter related posts
$recentPosts = $user->hasMany(Post::class, 'user_id')
    ->where('created_at', '>=', '2025-01-01')
    ->orderBy('id', 'DESC')
    ->limit(5)
    ->all();
```

Or start from a defined relationship key:

```php
$recentPosts = $user->posts
    ? array_filter($user->posts, fn($p) => $p->active)
    : [];
```

---

## 🧩 Summary of Relationship Types

| Type            | Direction | Example          | Returns         |
| --------------- | --------- | ---------------- | --------------- |
| `belongsTo`     | Reverse   | `$post->author`  | Single model    |
| `hasOne`        | Forward   | `$user->profile` | Single model    |
| `hasMany`       | Forward   | `$user->posts`   | Array of models |
| `belongsToMany` | Many-Many | `$post->tags`    | Array of models |

---

## 🧭 Next Steps

- [Models](./models.md) — Define your models and fields
- [Query Builder](./queries.md) — Learn how to query related data
