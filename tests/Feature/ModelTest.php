<?php

use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\FieldEnum;

class User extends Model
{
    protected static function fields(): array
    {
        return [
            'id' => self::Int(autoincrement: true, primaryKey: true),
            'name' => self::Text(notNull: true),
            'email' => self::Text(unique: true, notNull: true),
            'active' => self::Boolean(default: true),
            'created_at' => self::DateTime(default: 'CURRENT_TIMESTAMP'),
        ];
    }
}

class Post extends Model
{
    protected static function fields(): array
    {
        return [
            'id' => self::Int(autoincrement: true, primaryKey: true),
            'user_id' => self::Int(notNull: true),
            'title' => self::Text(notNull: true),
            'content' => self::Text(),
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

beforeEach(function () {
    Rubik::connect([
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]);
});

it('creates a table successfully', function () {
    expect(User::createTable(true))->toBeTrue();
});

it('inserts a single record', function () {
    User::createTable(true);

    $user = new User();
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    expect($user->save())->toBeTrue();
    expect($user->id)->not->toBeNull();
});

it('inserts multiple records', function () {
    User::createTable(true);

    $records = [
        ['name' => 'John Doe', 'email' => 'john@example.com'],
        ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
    ];

    expect(User::insertMany($records))->toBeTrue();

    $users = User::all();
    expect(count($users))->toBe(2);
});

it('updates a record', function () {
    User::createTable(true);

    $user = new User();
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    $user->save();

    $user->name = 'John Smith';
    expect($user->update())->toBeTrue();

    $updated = User::find($user->id);
    expect($updated->name)->toBe('John Smith');
});

it('deletes a record', function () {
    User::createTable(true);

    $user = new User();
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    $user->save();

    expect($user->delete())->toBeTrue();
    expect(User::find($user->id))->toBeNull();
});

it('retrieves all records', function () {
    User::createTable(true);

    User::insertMany([
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'Jane', 'email' => 'jane@example.com'],
    ]);

    $users = User::all();
    expect(count($users))->toBe(2);
});

it('finds a record by primary key', function () {
    User::createTable(true);

    $user = new User();
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    $user->save();

    $found = User::find($user->id);
    expect($found->email)->toBe('john@example.com');
});

it('finds one record by field', function () {
    User::createTable(true);

    $user = new User();
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    $user->save();

    $found = User::findOneBy('email', 'john@example.com');
    expect($found->name)->toBe('John Doe');
});

it('finds all records by field', function () {
    User::createTable(true);

    User::insertMany([
        ['name' => 'John', 'email' => 'john@example.com', 'active' => true],
        ['name' => 'Jane', 'email' => 'jane@example.com', 'active' => true],
    ]);

    $activeUsers = User::findAllBy('active', true);
    expect(count($activeUsers))->toBe(2);
});

it('handles relationships', function () {
    User::createTable(true);
    Post::createTable(true);

    $user = new User();
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    $user->save();

    $post = new Post();
    $post->user_id = $user->id;
    $post->title = 'Test Post';
    $post->content = 'Content';
    $post->save();

    $foundPost = Post::find($post->id);
    expect($foundPost->user)->not->toBeNull();
    expect($foundPost->user->email)->toBe('john@example.com');
});
