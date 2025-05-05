<?php

use AdaiasMagdiel\Rubik\Rubik;

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

it('paginates results correctly', function () {
    User::createTable(true);

    User::insertMany([
        ['name' => 'John', 'email' => 'john@example.com', 'active' => true],
        ['name' => 'Jane', 'email' => 'jane@example.com', 'active' => true],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'active' => false],
    ]);

    $result = User::paginate(1, 2);
    expect($result)->toBeInstanceOf(\stdClass::class);
    expect(count($result->data))->toBe(2);
    expect($result->current_page)->toBe(1);
    expect($result->per_page)->toBe(2);
    expect($result->total)->toBe(3);
    expect($result->last_page)->toBe(2);
    expect($result->data[0]->name)->toBe('John');
    expect($result->data[1]->name)->toBe('Jane');
});

it('paginates second page correctly', function () {
    User::createTable(true);

    User::insertMany([
        ['name' => 'John', 'email' => 'john@example.com', 'active' => true],
        ['name' => 'Jane', 'email' => 'jane@example.com', 'active' => true],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'active' => false],
    ]);

    $result = User::paginate(2, 2);
    expect($result)->toBeInstanceOf(\stdClass::class);
    expect(count($result->data))->toBe(1);
    expect($result->current_page)->toBe(2);
    expect($result->per_page)->toBe(2);
    expect($result->total)->toBe(3);
    expect($result->last_page)->toBe(2);
    expect($result->data[0]->name)->toBe('Bob');
});

it('paginates with specific fields', function () {
    User::createTable(true);

    User::insertMany([
        ['name' => 'John', 'email' => 'john@example.com', 'active' => true],
        ['name' => 'Jane', 'email' => 'jane@example.com', 'active' => true],
    ]);

    $result = User::paginate(1, 1, ['name']);
    expect($result)->toBeInstanceOf(\stdClass::class);
    expect(count($result->data))->toBe(1);
    expect($result->total)->toBe(2);
    expect($result->last_page)->toBe(2);
    expect($result->data[0]->name)->toBe('John');
    expect($result->data[0]->email)->toBeNull();
});

it('throws exception for invalid page number in pagination', function () {
    User::createTable(true);

    User::paginate(0, 2);
})->throws(\InvalidArgumentException::class, 'Page must be at least 1');

it('throws exception for invalid per page value in pagination', function () {
    User::createTable(true);

    User::paginate(1, 0);
})->throws(\InvalidArgumentException::class, 'PerPage must be at least 1');

it('serializes model data to JSON correctly', function () {
    User::createTable(true);

    $user = new User();
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    $user->save();

    $json = json_encode($user);
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKeys(['id', 'name', 'email']);
    expect($decoded['id'])->toBe($user->id);
    expect($decoded['name'])->toBe('John Doe');
    expect($decoded['email'])->toBe('john@example.com');
});
