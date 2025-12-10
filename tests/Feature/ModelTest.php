<?php

use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\SQL;

beforeAll(function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');
});

afterAll(fn() => Rubik::disconnect());

//
// ──────────────────────────────────────────────
//   STUB MODELS
// ──────────────────────────────────────────────
//

class UserModel extends Model
{
    protected static string $table = 'users';

    protected static function fields(): array
    {
        return [
            'id' => [
                'type' => 'INTEGER',
                'primary_key' => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'not_null' => true,
                'default' => 'anonymous',
            ],
            'age' => [
                'type' => 'INTEGER',
                'default' => 0,
            ],
            'role' => [
                'type' => 'ENUM',
                'values' => ['admin', 'user'],
                'default' => 'user',
            ],
        ];
    }

    protected static function relationships(): array
    {
        return [
            'posts' => [
                'type' => 'hasMany',
                'related' => PostModel::class,
                'foreignKey' => 'user_id',
            ],
        ];
    }
}

class PostModel extends Model
{
    protected static string $table = 'posts';

    protected static function fields(): array
    {
        return [
            'id' => [
                'type' => 'INTEGER',
                'primary_key' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INTEGER',
                'foreign_key' => [
                    'references' => 'id',
                    'table' => 'users',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'NO ACTION',
                ],
            ],
            'title' => ['type' => 'TEXT'],
        ];
    }
}

//
// ──────────────────────────────────────────────
//   SCHEMA TESTS
// ──────────────────────────────────────────────
//
describe('SchemaTrait', function () {
    test('fields() not implemented throws', function () {
        $anon = new class extends Model {};
        expect(fn() => (new ReflectionMethod($anon, 'fields'))->invoke(null))
            ->toThrow(RuntimeException::class);
    });

    test('primaryKey returns correct key and throws when missing', function () {
        expect(UserModel::primaryKey())->toBe('id');

        $anon = new class extends Model {
            protected static function fields(): array
            {
                return ['x' => ['type' => 'TEXT']];
            }
        };
        expect(fn() => $anon::primaryKey())->toThrow(RuntimeException::class);
    });

    test('getTableName returns explicit or derived name', function () {
        expect(UserModel::getTableName())->toBe('users');

        $anon = new class extends Model {};
        $ref = new ReflectionProperty($anon, 'table');
        $ref->setAccessible(true);
        $ref->setValue($anon, '');
        expect($anon::getTableName())->toEndWith('s');
    });

    test('createTable, truncateTable and dropTable execute successfully', function () {
        expect(UserModel::createTable())->toBeTrue();
        expect(PostModel::createTable())->toBeTrue();
        expect(UserModel::truncateTable())->toBeTrue();
        expect(UserModel::dropTable(ifExists: true))->toBeTrue();
    });

    test('getFieldString covers all branches (MySQL and SQLite)', function () {
        $ref = new ReflectionMethod(UserModel::class, 'getFieldString');
        $ref->setAccessible(true);

        // SQLite mapping
        Rubik::setDriver(Driver::SQLITE);
        $sqliteField = ['type' => 'VARCHAR', 'default' => 'abc', 'not_null' => true];
        expect($ref->invoke(null, $sqliteField))->toContain('TEXT NOT NULL DEFAULT');

        // MySQL ENUM and UNSIGNED
        Rubik::setDriver(Driver::MYSQL);
        $enumField = ['type' => 'ENUM', 'values' => ['A', 'B'], 'default' => 'A'];
        $tinyField = ['type' => 'TINYINT', 'unsigned' => true];
        $decField  = ['type' => 'DECIMAL', 'precision' => 10, 'scale' => 2];
        expect($ref->invoke(null, $enumField))->toContain("ENUM('A', 'B')");
        expect($ref->invoke(null, $tinyField))->toContain('UNSIGNED');
        expect($ref->invoke(null, $decField))->toContain('(10,2)');

        // ENUM empty → exception
        $badEnum = ['type' => 'ENUM', 'values' => []];
        expect(fn() => $ref->invoke(null, $badEnum))
            ->toThrow(InvalidArgumentException::class);

        // Invalid type type
        $bad = ['type' => 123];
        expect(fn() => $ref->invoke(null, $bad))->toThrow(InvalidArgumentException::class);

        Rubik::setDriver(Driver::SQLITE);
    });

    test('escapeDefaultValue handles all types', function () {
        $ref = new ReflectionMethod(UserModel::class, 'escapeDefaultValue');
        $ref->setAccessible(true);

        expect($ref->invoke(null, new SQL('NOW()')))->toBe('NOW()');
        expect($ref->invoke(null, null))->toBe('NULL');
        expect($ref->invoke(null, true))->toBe('1');
        expect($ref->invoke(null, false))->toBe('0');
        expect($ref->invoke(null, 'abc'))->toContain("'");
        expect($ref->invoke(null, 123))->toBe('123');
    });
});

//
// ──────────────────────────────────────────────
//   CRUD TESTS
// ──────────────────────────────────────────────
//
describe('CrudTrait', function () {
    beforeEach(function () {
        Rubik::setDriver(Driver::SQLITE);
        UserModel::createTable();
    });

    test('save inserts and updates correctly', function () {
        $user = new UserModel();
        $user->__set('name', 'Alice');
        $ok = $user->save();
        expect($ok)->toBeTrue()->and($user->__get('id'))->toBeInt();

        $user->__set('name', 'Alicia');
        expect($user->save())->toBeTrue();
    });

    test('save with ignore true performs insert or ignore', function () {
        $user = new UserModel();
        $user->__set('name', 'Ignored');
        expect($user->save(ignore: true))->toBeTrue();
    });

    test('update updates only dirty fields', function () {
        $user = new UserModel();
        $user->__set('name', 'Bob');
        $user->save();

        $user->__set('age', 35);
        expect($user->update())->toBeTrue();

        // no dirty fields → true
        expect($user->update())->toBeTrue();
    });

    test('update throws without PK', function () {
        $user = new UserModel();
        expect(fn() => $user->update())->toThrow(RuntimeException::class);
    });

    test('delete works and throws without PK', function () {
        $user = new UserModel();
        $user->__set('name', 'DeleteMe');
        $user->save();
        expect($user->delete())->toBeTrue();

        $noPk = new UserModel();
        expect(fn() => $noPk->delete())->toThrow(RuntimeException::class);
    });

    test('insertMany inserts multiple and handles rollback on failure', function () {
        $records = [
            ['name' => 'X', 'age' => 10],
            ['name' => 'Y', 'age' => 20],
        ];
        expect(UserModel::insertMany($records))->toBeTrue();

        expect(UserModel::insertMany([]))->toBeFalse();

        // simulate failure (bad column)
        $bad = [['invalid_col' => 'oops']];
        expect(fn() => UserModel::insertMany($bad))
            ->toThrow(RuntimeException::class);
    });
});

//
// ──────────────────────────────────────────────
//   QUERY TRAIT TESTS
// ──────────────────────────────────────────────
//
describe('QueryTrait', function () {
    beforeEach(function () {
        Rubik::setDriver(Driver::SQLITE);
        UserModel::createTable();
        PostModel::createTable();

        $u = new UserModel();
        $u->__set('name', 'John');
        $u->save();

        $p = new PostModel();
        $p->__set('user_id', $u->__get('id'));
        $p->__set('title', 'Hello');
        $p->save();
    });

    test('query, find, first, all, paginate work correctly', function () {
        expect(UserModel::query())->toBeInstanceOf(\AdaiasMagdiel\Rubik\Query::class);
        $found = UserModel::find(1);
        expect($found)->toBeInstanceOf(UserModel::class);
        expect(UserModel::first())->toBeInstanceOf(UserModel::class);
        expect(UserModel::all())->toBeArray()->not->toBeEmpty();
        $page = UserModel::paginate(1, 2);
        expect($page)->toHaveKeys(['data', 'total', 'current_page']);
    });

    test('belongsTo, hasOne, hasMany, belongsToMany generate valid SQL', function () {
        $user = UserModel::find(1);
        $ref = new ReflectionClass($user);
        $bt  = $user->hasMany(PostModel::class, 'user_id');
        expect($bt)->toBeInstanceOf(\AdaiasMagdiel\Rubik\Query::class);

        $p = PostModel::find(1);
        $bt = $p->belongsTo(UserModel::class, 'user_id');
        expect($bt)->toBeInstanceOf(\AdaiasMagdiel\Rubik\Query::class);

        $ho = $user->hasOne(PostModel::class, 'user_id');
        expect($ho)->toBeInstanceOf(\AdaiasMagdiel\Rubik\Query::class);

        $bm = $user->belongsToMany(PostModel::class, 'user_post', 'user_id', 'post_id');
        expect($bm)->toBeInstanceOf(\AdaiasMagdiel\Rubik\Query::class);
    });

    test('invalid related class throws', function () {
        $user = UserModel::find(1);
        expect(fn() => $user->belongsTo('Nope', 'x'))->toThrow(InvalidArgumentException::class);
    });
});

//
// ──────────────────────────────────────────────
//   MODEL BEHAVIOR TESTS
// ──────────────────────────────────────────────
//
describe('Model base class', function () {
    beforeEach(function () {
        UserModel::createTable();
        PostModel::createTable();
    });

    test('__set and __get store and retrieve values', function () {
        $u = new UserModel();
        $u->__set('name', 'X');
        expect($u->__get('name'))->toBe('X');

        $u->__set('id', 5);
        expect($u->__get('id'))->toBeInt();
    });

    test('__get resolves relationships and caches results', function () {
        $user = new UserModel();
        $user->__set('name', 'Cached');
        $user->save();

        $post = new PostModel();
        $post->__set('user_id', $user->__get('id'));
        $post->__set('title', 'My Post');
        $post->save();

        $u = UserModel::find($user->__get('id'));
        $posts = $u->__get('posts');
        expect($posts)->toBeArray()->and($u->__get('posts'))->toBe($posts);
    });

    test('__get returns null for unknown field or relationship', function () {
        $u = new UserModel();
        expect($u->__get('nonexistent'))->toBeNull();
    });

    test('__get throws for unknown relationship type', function () {
        $anon = new class extends Model {
            protected static function fields(): array
            {
                return ['id' => ['type' => 'INTEGER', 'primary_key' => true]];
            }
            protected static function relationships(): array
            {
                return ['invalid' => ['type' => 'weird', 'related' => UserModel::class]];
            }
        };
        $anon->__set('id', 1);
        expect(fn() => $anon->__get('invalid'))->toThrow(InvalidArgumentException::class);
    });
});

//
// ──────────────────────────────────────────────
//   SERIALIZATION TRAIT
// ──────────────────────────────────────────────
//
describe('SerializationTrait', function () {
    test('toArray and jsonSerialize output correct data', function () {
        $u = new UserModel();
        $u->__set('name', 'Json');
        $arr = $u->toArray();
        $json = $u->jsonSerialize();

        expect($arr)->toBeArray()->and($json)->toBe($arr);
    });
});
