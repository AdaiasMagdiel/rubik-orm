# Relationships & Associations

Rubik provides a robust relationship system to link models together. Relationships are defined as methods returning a `Relation` object.

## Relationship Types

### 1. HasOne / HasMany

The **Inverse** side holds the foreign key.

- **Parent:** `User` (ID: 1)
- **Child:** `Post` (user_id: 1)

**User Model:**

```php
public function posts(): HasMany {
    // (RelatedClass, ForeignKeyOnChild, LocalKeyOnParent)
    return $this->hasMany(Post::class, 'user_id', 'id');
}
```

### 2. BelongsTo

The **Owning** side holds the foreign key.

**Post Model:**

```php
public function user(): BelongsTo {
    // (RelatedClass, ForeignKeyOnThis, OwnerKeyOnParent)
    return $this->belongsTo(User::class, 'user_id', 'id');
}
```

### 3. BelongsToMany (Many-to-Many)

Requires a Pivot Table (e.g., `role_user`).

**User Model:**

```php
public function roles(): BelongsToMany {
    return $this->belongsToMany(
        related: Role::class,
        pivotTable: 'role_user',
        foreignKey: 'user_id',    // Key in pivot pointing to This
        relatedKey: 'role_id',    // Key in pivot pointing to Related
        parentKey: 'id',          // Key on This
        relatedParentKey: 'id'    // Key on Related
    );
}
```

!!! note "Pivot Table Names"
Unlike some ORMs, Rubik requires you to explicitly name the pivot table. It does not guess alphabetical order (e.g., `role_user` vs `user_role`).

## Loading Strategies

### Lazy Loading (Magic Property)

Accessing the relationship as a property triggers a database query immediately. The result is cached on the model instance.

```php
$user = User::find(1);
// Query runs here: SELECT * FROM posts WHERE user_id = 1
$posts = $user->posts;
```

### Eager Loading (`with`)

Solves the N+1 query problem. Rubik loads all related models in one go and maps them in memory.

```php
$users = User::query()->with('posts', 'profile')->all();

foreach ($users as $user) {
    // No DB query here. $user->posts is already populated.
    print_r($user->posts);
}
```

### How Eager Loading Works Internally

1.  **Extract Keys:** Rubik gathers all IDs from the parent `$users` list.
2.  **Batch Query:** It runs `SELECT * FROM posts WHERE user_id IN (1, 2, 3...)`.
3.  **Dictionary Map:** It iterates the posts, grouping them by `user_id`.
4.  **Hydration:** It assigns the groups back to the specific User instances using `setRelation()`.

## Chaining on Relations

Since Relationships act as Query Builders, you can refine them:

```php
// Get only published posts for this user
$published = $user->posts()->where('status', 'published')->get();
```
