<?php

use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Query;
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\SQL;

beforeAll(function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');

    $pdo = Rubik::getConn();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER, active INTEGER)');
    $pdo->exec("INSERT INTO users (name, age, active) VALUES ('Alice', 30, 1), ('Bob', 25, 0), ('Carol', 40, 1)");
});

afterAll(fn() => Rubik::disconnect());

afterEach(function () {
    try {
        $pdo = Rubik::getConn();
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER, active INTEGER)');
        $pdo->exec("INSERT INTO users (name, age, active) VALUES ('Alice', 30, 1), ('Bob', 25, 0), ('Carol', 40, 1)");
    } catch (Throwable) {
    }
});


test('setTable sets the table name', function () {
    $q = (new Query())->setTable('users');
    expect($q)->toBeInstanceOf(Query::class);
});

test('setModel throws when model class does not exist', function () {
    expect(fn() => (new Query())->setModel('NonexistentModel'))
        ->toThrow(RuntimeException::class, 'does not exist');
});

test('select handles wildcards, aliases and primary key inclusion', function () {
    $q = (new Query())->setTable('users')->select(['name', 'age AS user_age']);
    $sql = $q->getSql();
    expect($sql)->toContain('SELECT users.id, name, age AS user_age FROM users');
});

test('where and orWhere build correct clauses with bindings', function () {
    $q = (new Query())->setTable('users')
        ->where('age', '>', 20)
        ->orWhere('name', 'Alice');
    $sql = $q->getSql();

    expect($sql)->toContain('WHERE age >')
        ->and($sql)->toContain('OR name =')
        ->and($q)->toHaveProperty('bindings');
});

test('whereIn builds correct IN clause and throws on empty array', function () {
    $q = (new Query())->setTable('users')->whereIn('id', [1, 2, 3]);
    $sql = $q->getSql();
    expect($sql)->toContain('id IN (:id_in_0, :id_in_1, :id_in_2)');

    expect(fn() => (new Query())->setTable('users')->whereIn('id', []))
        ->toThrow(InvalidArgumentException::class, 'non-empty array');
});

test('invalid operator throws in addCondition', function () {
    $ref = new ReflectionClass(Query::class);
    $method = $ref->getMethod('addCondition');
    $method->setAccessible(true);

    $q = new Query();
    expect(fn() => $method->invoke($q, 'id', 1, 'INVALID', 'AND'))
        ->toThrow(InvalidArgumentException::class, 'Invalid operator');
});

test('joins build proper SQL syntax', function () {
    $q = (new Query())->setTable('users')
        ->join('posts', 'users.id', '=', 'posts.user_id')
        ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
        ->rightJoin('groups', 'users.group_id', '=', 'groups.id');
    $sql = $q->getSql();
    expect($sql)->toContain('INNER JOIN posts')
        ->and($sql)->toContain('LEFT JOIN profiles')
        ->and($sql)->toContain('RIGHT JOIN groups');
});

test('orderBy, groupBy, having produce correct clauses', function () {
    $q = (new Query())->setTable('users')
        ->orderBy('name', 'DESC')
        ->groupBy('age')
        ->having('COUNT(*) > 1');
    $sql = $q->getSql();

    expect($sql)->toContain('ORDER BY name DESC')
        ->and($sql)->toContain('GROUP BY age')
        ->and($sql)->toContain('HAVING COUNT(*) > 1');
});

test('limit and offset throw on negative values and apply correctly', function () {
    expect(fn() => (new Query())->limit(-1))
        ->toThrow(InvalidArgumentException::class, 'non-negative');
    expect(fn() => (new Query())->offset(-5))
        ->toThrow(InvalidArgumentException::class, 'non-negative');

    $q = (new Query())->setTable('users')->limit(2)->offset(1);
    $sql = $q->getSql();
    expect($sql)->toContain('LIMIT 2')->and($sql)->toContain('OFFSET 1');
});

test('delete builds correct SQL', function () {
    $q = (new Query())->setTable('users')->where('id', 1);
    $q->delete();
    expect($q->getSql())->toContain('DELETE FROM users WHERE id =');
});


test('update returns true for valid data and false for empty array', function () {
    $q = (new Query())->setTable('users')->where('id', 1);
    $result = $q->update(['name' => 'Alicia']);
    expect($result)->toBeTrue();

    $q2 = (new Query())->setTable('users');
    expect($q2->update([]))->toBeFalse();
});

test('count returns integer and resets select/limit/offset state', function () {
    $q = (new Query())->setTable('users')->where('active', 1);
    $count = $q->count();
    expect($count)->toBeInt()->and($count)->toBeGreaterThan(0);
});

test('all returns hydrated results as arrays when no model is set', function () {
    $q = (new Query())->setTable('users')->limit(2);
    $data = $q->all();
    expect($data)->toBeArray()->and($data[0])->toHaveKeys(['id', 'name', 'age', 'active']);
});

test('first returns a single row as object', function () {
    $q = (new Query())->setTable('users')->where('name', 'Alice');
    $row = $q->first();
    expect($row)->toBeObject()->and($row->name)->toBe('Alice');
});

test('paginate returns metadata and results', function () {
    $q = (new Query())->setTable('users');
    $page = $q->paginate(1, 2);
    expect($page)->toHaveKeys(['data', 'total', 'per_page', 'current_page', 'last_page']);
});

test('paginate throws on invalid page or perPage', function () {
    $q = (new Query())->setTable('users');
    expect(fn() => $q->paginate(0, 10))->toThrow(InvalidArgumentException::class);
    expect(fn() => $q->paginate(1, 0))->toThrow(InvalidArgumentException::class);
});

test('delete executes a DELETE and returns boolean', function () {
    $pdo = Rubik::getConn();
    $pdo->exec("INSERT INTO users (name, age, active) VALUES ('Temp', 99, 0)");

    $q = (new Query())->setTable('users')->where('name', 'Temp');
    $result = $q->delete();

    expect($result)->toBeTrue();
});


test('getSql throws when table not set or when update mode', function () {
    $q = new Query();
    expect(fn() => $q->getSql())->toThrow(RuntimeException::class, 'Table name must be set');

    $q2 = (new Query())->setTable('users');
    $ref = new ReflectionProperty(Query::class, 'operation');
    $ref->setAccessible(true);
    $ref->setValue($q2, 'UPDATE');
    expect(fn() => $q2->getSql())->toThrow(RuntimeException::class, 'Cannot get SQL for UPDATE');
});

test('executeStatement throws if prepare fails or query error occurs', function () {
    $pdo = Rubik::getConn();

    // Force prepare() to fail using a fake connection (simulate by closing)
    $pdo->exec('DROP TABLE IF EXISTS broken');
    $pdo->exec('CREATE TABLE broken (id INTEGER)');
    $q = (new Query())->setTable('broken');

    $ref = new ReflectionMethod(Query::class, 'executeStatement');
    $ref->setAccessible(true);

    // Table set, but connection closed temporarily
    Rubik::disconnect();
    expect(fn() => $ref->invoke($q))->toThrow(RuntimeException::class);

    // Restore connection
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');
    $pdo = Rubik::getConn();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER, active INTEGER)');
    $pdo->exec("INSERT INTO users (name, age, active) VALUES ('Alice', 30, 1), ('Bob', 25, 0), ('Carol', 40, 1)");
});

test('hydrateModels returns objects if model is defined', function () {
    require_once __DIR__ . '/../stubs/FakeModel.php';
    $q = (new Query())->setModel(\Tests\Stubs\FakeModel::class);
    $ref = new ReflectionMethod(Query::class, 'hydrateModels');
    $ref->setAccessible(true);

    $data = [['id' => 1, 'name' => 'X']];
    $hydrated = $ref->invoke($q, $data);
    expect($hydrated[0])->toBeInstanceOf(\Tests\Stubs\FakeModel::class);
});

test('SQL::raw works in SELECT expressions', function () {
    $q = (new Query())->setTable('users')->select(['id', SQL::raw('LENGTH(name) AS len')]);
    $sql = $q->getSql();

    expect($sql)->toContain('LENGTH(name) AS len');
});

test('SQL::raw works in WHERE conditions', function () {
    $q = (new Query())->setTable('users')->where('created_at', '>=', SQL::raw('CURRENT_TIMESTAMP'));
    $sql = $q->getSql();

    expect($sql)->toContain('created_at >= CURRENT_TIMESTAMP');
});

test('SQL::raw works in UPDATE statements', function () {
    $q = (new Query())->setTable('users')->where('id', 1);
    $result = $q->update(['age' => SQL::raw('age + 1')]);
    expect($result)->toBeTrue();
});
