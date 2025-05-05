<?php

use AdaiasMagdiel\Rubik\Rubik;

beforeEach(function () {
    Rubik::connect([
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]);

    User::createTable(true);
    User::insertMany([
        ['name' => 'John', 'email' => 'john@example.com', 'active' => true],
        ['name' => 'Jane', 'email' => 'jane@example.com', 'active' => true],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'active' => false],
    ]);
});

it('selects specific fields', function () {
    $users = User::query()->select(['name', 'email'])->all();
    expect(count($users))->toBeGreaterThan(0);
    expect($users[0]->name)->toBeString();
    expect($users[0]->email)->toBeString();
});

it('applies where conditions', function () {
    $user = User::query()->where('email', 'john@example.com')->first();
    expect($user->name)->toBe('John');
});

it('applies orWhere conditions', function () {
    $users = User::query()
        ->where('email', 'john@example.com')
        ->orWhere('email', 'jane@example.com')
        ->all();
    expect(count($users))->toBe(2);
});

it('handles whereIn conditions', function () {
    $users = User::query()->whereIn('email', [
        'john@example.com',
        'jane@example.com'
    ])->all();
    expect(count($users))->toBe(2);
});

it('limits results', function () {
    $users = User::query()->limit(1)->all();
    expect(count($users))->toBe(1);
});

it('offsets results', function () {
    $users = User::query()->limit(1)->offset(1)->all();
    expect($users[0]->name)->toBe('Jane');
});

it('orders results', function () {
    $users = User::query()->orderBy('name', 'DESC')->all();
    expect($users[0]->name)->toBe('John');
});

it('groups results', function () {
    $users = User::query()
        ->select('active')
        ->groupBy('active')
        ->all();
    expect(count($users))->toBe(2); // true and false groups
});

it('deletes records', function () {
    expect(User::query()->where('active', false)->delete()->exec())->toBeTrue();
    expect(User::all())->toHaveCount(2);
});

it('updates records', function () {
    expect(
        User::query()
            ->where('email', 'john@example.com')
            ->update(['name' => 'John Updated'])
    )->toBeTrue();

    $user = User::findOneBy('email', 'john@example.com');
    expect($user->name)->toBe('John Updated');
});

it('paginates results correctly', function () {
    $result = User::query()->paginate(1, 2);
    expect($result['data'])->toHaveCount(2);
    expect($result['current_page'])->toBe(1);
    expect($result['per_page'])->toBe(2);
    expect($result['total'])->toBe(3);
    expect($result['last_page'])->toBe(2);
    expect($result['data'][0]->name)->toBe('John');
    expect($result['data'][1]->name)->toBe('Jane');
});

it('paginates second page correctly', function () {
    $result = User::query()->paginate(2, 2);
    expect($result['data'])->toHaveCount(1);
    expect($result['current_page'])->toBe(2);
    expect($result['per_page'])->toBe(2);
    expect($result['total'])->toBe(3);
    expect($result['last_page'])->toBe(2);
    expect($result['data'][0]->name)->toBe('Bob');
});

it('paginates with where conditions', function () {
    $result = User::query()->where('active', true)->paginate(1, 1);
    expect($result['data'])->toHaveCount(1);
    expect($result['total'])->toBe(2);
    expect($result['last_page'])->toBe(2);
    expect($result['data'][0]->name)->toBe('John');
});

it('throws exception for invalid page number', function () {
    User::query()->paginate(0, 2);
})->throws(\InvalidArgumentException::class, 'Page must be at least 1');

it('throws exception for invalid per page value', function () {
    User::query()->paginate(1, 0);
})->throws(\InvalidArgumentException::class, 'PerPage must be at least 1');
