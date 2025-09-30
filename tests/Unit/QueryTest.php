<?php

use AdaiasMagdiel\Rubik\Column;
use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Query;
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\SQL;

// Define test models for query testing
class QueryTestUser extends Model
{
    public static string $table = 'users';

    public static function fields(): array
    {
        return [
            'id' => Column::Int(autoincrement: true, primaryKey: true),
            'name' => Column::Varchar(notNull: true),
            'email' => Column::Varchar(unique: true, notNull: true),
            'age' => Column::Int(default: 18),
            'is_active' => Column::Boolean(default: true),
            'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
        ];
    }
}

class QueryTestPost extends Model
{
    public static string $table = 'posts';

    public static function fields(): array
    {
        return [
            'id' => Column::Int(autoincrement: true, primaryKey: true),
            'user_id' => Column::Int(notNull: true),
            'title' => Column::Varchar(notNull: true),
            'content' => Column::Text(),
            'likes' => Column::Int(default: 0),
        ];
    }
}

// Setup: Connect to in-memory SQLite before each test
beforeEach(function () {
    Rubik::connect(Driver::SQLITE, path: ':memory:');
});

// Teardown: Disconnect after each test to reset state
afterEach(function () {
    Rubik::disconnect();
});

// Helper to populate users table
function populateUsers(int $count = 5): void
{
    QueryTestUser::createTable();
    $records = [];
    for ($i = 1; $i <= $count; $i++) {
        $records[] = [
            'name' => "User $i",
            'email' => "user$i@example.com",
            'age' => 20 + $i,
            'is_active' => $i % 2 === 0,
        ];
    }
    QueryTestUser::insertMany($records);
}

// Helper to populate posts table
function populatePosts(int $count = 3): void
{
    QueryTestUser::createTable(); // Ensure users table exists
    QueryTestPost::createTable();
    $records = [];
    for ($i = 1; $i <= $count; $i++) {
        $records[] = [
            'user_id' => $i, // Ensure user_id matches existing users
            'title' => "Post $i",
            'content' => "Content for post $i",
            'likes' => $i * 10,
        ];
    }
    QueryTestPost::insertMany($records);
}

// Basic Construction Tests

test('can set table name', function () {
    $query = (new Query())->setTable('users');
    expect($query->getSql())->toContain('SELECT * FROM users');
});

test('can set model and derive table name', function () {
    $query = (new Query())->setModel(QueryTestUser::class);
    expect($query->getSql())->toContain('SELECT * FROM users');
});

test('throws exception if model does not exist', function () {
    (new Query())->setModel('NonExistentModel');
})->throws(RuntimeException::class, 'Model class NonExistentModel does not exist');

// Select Tests

test('can select specific fields', function () {
    $query = (new Query())->setTable('users')->select(['name', 'email']);
    $sql = $query->getSql();
    expect($sql)->toContain('SELECT users.id, name, email FROM users');
});

test('can select all fields with asterisk', function () {
    $query = (new Query())->setTable('users')->select('*');
    expect($query->getSql())->toContain('SELECT * FROM users');
});

// Where Conditions Tests

test('can add where condition', function () {
    QueryTestUser::createTable();
    $query = (new Query())->setTable('users')->where('age', 25);
    $sql = $query->getSql();
    expect($sql)->toContain('WHERE age = :age_0');
    expect($query->all())->toBeEmpty(); // No data
});

test('can add orWhere condition', function () {
    $query = (new Query())->setTable('users')->where('age', 25)->orWhere('name', 'John');
    $sql = $query->getSql();
    expect($sql)->toContain('WHERE age = :age_0 OR name = :name_1');
});

test('can add whereIn condition', function () {
    $query = (new Query())->setTable('users')->whereIn('id', [1, 2, 3]);
    $sql = $query->getSql();
    expect($sql)->toContain('WHERE id IN (:id_in_0, :id_in_1, :id_in_2)');
});

test('throws exception for empty whereIn array', function () {
    (new Query())->setTable('users')->whereIn('id', []);
})->throws(InvalidArgumentException::class, 'whereIn requires a non-empty array');

// Join Tests

test('can add inner join', function () {
    $query = (new Query())->setTable('users')->join('posts', 'users.id', '=', 'posts.user_id');
    $sql = $query->getSql();
    expect($sql)->toContain('INNER JOIN posts ON users.id = posts.user_id');
});

test('can add left join', function () {
    $query = (new Query())->setTable('users')->leftJoin('posts', 'users.id', '=', 'posts.user_id');
    $sql = $query->getSql();
    expect($sql)->toContain('LEFT JOIN posts ON users.id = posts.user_id');
});

test('can add right join', function () {
    $query = (new Query())->setTable('users')->rightJoin('posts', 'users.id', '=', 'posts.user_id');
    $sql = $query->getSql();
    expect($sql)->toContain('RIGHT JOIN posts ON users.id = posts.user_id');
});

// Order By, Group By, Having Tests

test('can add order by', function () {
    $query = (new Query())->setTable('users')->orderBy('name', 'DESC');
    $sql = $query->getSql();
    expect($sql)->toContain('ORDER BY name DESC');
});

test('can add group by', function () {
    $query = (new Query())->setTable('users')->groupBy('age');
    $sql = $query->getSql();
    expect($sql)->toContain('GROUP BY age');
});

test('can add having', function () {
    $query = (new Query())->setTable('users')->groupBy('age')->having('COUNT(*) > 1');
    $sql = $query->getSql();
    expect($sql)->toContain('HAVING COUNT(*) > 1');
});

// Limit and Offset Tests

test('can set limit', function () {
    $query = (new Query())->setTable('users')->limit(5);
    $sql = $query->getSql();
    expect($sql)->toContain('LIMIT 5');
});

test('throws exception for negative limit', function () {
    (new Query())->setTable('users')->limit(-1);
})->throws(InvalidArgumentException::class, 'Limit must be non-negative');

test('can set offset', function () {
    $query = (new Query())->setTable('users')->offset(10);
    $sql = $query->getSql();
    expect($sql)->toContain('OFFSET 10');
});

test('throws exception for negative offset', function () {
    (new Query())->setTable('users')->offset(-1);
})->throws(InvalidArgumentException::class, 'Offset must be non-negative');

// Delete and Update Tests

test('can set delete operation', function () {
    $query = (new Query())->setTable('users')->delete();
    expect($query->getSql())->toContain('DELETE FROM users');
});

test('can execute delete with where', function () {
    populateUsers(1);
    $query = (new Query())->setTable('users')->where('id', 1)->delete();
    expect($query->exec())->toBeTrue();
    expect(QueryTestUser::all())->toBeEmpty();
});

test('can execute update', function () {
    populateUsers(1);
    $query = (new Query())->setTable('users')->where('id', 1);
    expect($query->update(['name' => 'Updated Name']))->toBeTrue();
    $user = QueryTestUser::find(1);
    expect($user->name)->toBe('Updated Name');
});

// All, First, Exec Tests

test('can get all results', function () {
    populateUsers(3);
    $query = (new Query())->setModel(QueryTestUser::class);
    $results = $query->all();
    expect($results)->toHaveCount(3);
    expect($results[0])->toBeInstanceOf(QueryTestUser::class);
    expect($results[0]->name)->toBe('User 1');
});

test('can get first result', function () {
    populateUsers(2);
    $query = (new Query())->setModel(QueryTestUser::class);
    $first = $query->first();
    expect($first)->toBeInstanceOf(QueryTestUser::class);
    expect((int)$first->id)->toBe(1);
    expect($first->name)->toBe('User 1');
});

test('first returns null if no results', function () {
    QueryTestUser::createTable();
    $query = (new Query())->setModel(QueryTestUser::class);
    expect($query->first())->toBeNull();
});

test('can exec delete', function () {
    populateUsers(1);
    $query = (new Query())->setTable('users')->delete();
    expect($query->exec())->toBeTrue();
    expect(QueryTestUser::all())->toBeEmpty();
});

test('exec returns false if no rows affected', function () {
    QueryTestUser::createTable();
    $query = (new Query())->setTable('users')->where('id', 999)->delete();
    expect($query->exec())->toBeFalse();
});

// Paginate Tests

test('can paginate results', function () {
    populateUsers(10);
    $query = (new Query())->setModel(QueryTestUser::class);
    $page1 = $query->paginate(1, 5);
    expect($page1['data'])->toHaveCount(5);
    expect($page1['data'][0]->name)->toBe('User 1');
    expect($page1['total'])->toBe(10);
    expect($page1['last_page'])->toBe(2);
});

test('paginate throws for invalid page or perPage', function () {
    $query = (new Query())->setTable('users');
    expect(fn() => $query->paginate(0, 5))->toThrow(InvalidArgumentException::class);
    expect(fn() => $query->paginate(1, 0))->toThrow(InvalidArgumentException::class);
});

// Get SQL Tests

test('can get generated sql', function () {
    $query = (new Query())->setTable('users')->where('age', 25)->limit(1);
    $sql = $query->getSql();
    expect($sql)->toBeString();
    expect($sql)->toBe('SELECT * FROM users WHERE age = :age_0 LIMIT 1');
});

// Complex Query Tests

test('can execute complex query with joins and hydration', function () {
    // Populate users with specific ages to ensure matches with age > 22
    QueryTestUser::createTable();
    $users = [
        ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 23],
        ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 24],
        ['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 20],
    ];
    QueryTestUser::insertMany($users);

    // Populate posts with matching user_ids for User 1 (age 23)
    QueryTestPost::createTable();
    $posts = [
        ['user_id' => 1, 'title' => 'Post 1', 'content' => 'Content 1', 'likes' => 10],
        ['user_id' => 1, 'title' => 'Post 3', 'content' => 'Content 3', 'likes' => 30],
        ['user_id' => 2, 'title' => 'Post 2', 'content' => 'Content 2', 'likes' => 20],
    ];
    QueryTestPost::insertMany($posts);

    // Debug: Run query without model to inspect raw results
    $debugQuery = (new Query())->setTable('users')
        ->select(['users.name', 'posts.title AS post_title'])
        ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
        ->where('users.age', '>', 22)
        ->orderBy('users.name', 'ASC')
        ->limit(2);
    $rawResults = $debugQuery->all();
    expect($rawResults)->toHaveCount(2, 'Raw results count mismatch: ' . json_encode($rawResults));
    expect($rawResults[0]['post_title'])->toBe('Post 1', 'Raw post_title[0] mismatch');
    expect($rawResults[1]['post_title'])->toBe('Post 3', 'Raw post_title[1] mismatch');

    // Main query with model
    $query = (new Query())->setModel(QueryTestUser::class)
        ->select(['users.name', 'posts.title AS post_title'])
        ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
        ->where('users.age', '>', 22)
        ->orderBy('users.name', 'ASC')
        ->limit(2);


    $results = $query->all();
    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(QueryTestUser::class);
    expect($results[0]->name)->toBe('User 1');
    expect($results[0]->post_title)->toBe('Post 1');
    expect($results[1]->name)->toBe('User 1');
    expect($results[1]->post_title)->toBe('Post 3');
});

// Edge Cases

test('throws exception if table not set', function () {
    (new Query())->getSql();
})->throws(RuntimeException::class, 'Table name must be set');

test('update with no data returns false', function () {
    populateUsers(1);
    $query = (new Query())->setTable('users')->where('id', 1);
    expect($query->update([]))->toBeFalse();
});

test('hydrate without model returns arrays', function () {
    populateUsers(1);
    $query = (new Query())->setTable('users');
    $results = $query->all();
    expect($results[0])->toBeArray();
    expect($results[0]['name'])->toBe('User 1');
});
